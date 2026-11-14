<?php
// Pont avec gestion-hotel (base laravel_hotel) : crée le compte et l'espace de gestion de chaque hôtel de la page welcome
declare(strict_types=1);

define('GESTION_BASE_URL', rtrim(env_or('GESTION_BASE_URL', 'http://localhost:8000'), '/')); // site de gestion
define('GESTION_LOGIN_URL', GESTION_BASE_URL . '/login');
define('GESTION_DB', env_or('GESTION_DB', 'laravel_hotel'));
const GESTION_STATUTS = ['en_attente' => 'pending', 'actif' => 'active', 'suspendu' => 'suspended'];

function gestion_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . GESTION_DB . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function gestion_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $p = '';
    for ($i = 0; $i < 10; $i++) {
        $p .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $p;
}

/**
 * État d'accès automatique d'un hôtel à son espace de gestion :
 *  - suspendu par l'admin            -> suspended (manuel)
 *  - en attente / jamais payé        -> pending
 *  - publicité payée et en cours     -> active
 *  - période payée terminée          -> suspended (expiré)
 * Le paiement active donc l'hôtel tout seul et l'échéance le suspend tout seul.
 *
 * @return array{status:string, reason:string}
 */
function gestion_access_state(array $h): array
{
    if ($h['statut'] === 'suspendu') {
        return ['status' => 'suspended', 'reason' => 'manual'];
    }
    if ($h['statut'] === 'en_attente') {
        return ['status' => 'pending', 'reason' => 'validation'];
    }
    if (!$h['pub_fin']) {
        return ['status' => 'pending', 'reason' => 'payment'];
    }
    return $h['pub_fin'] >= date('Y-m-d') ? ['status' => 'active', 'reason' => 'paid'] : ['status' => 'suspended', 'reason' => 'expired'];
}

/**
 * Crée ou met à jour l'espace de gestion d'un hôtel (tenant, hôtel, compte propriétaire, types de chambres et chambres).
 * Idempotent. $resetPassword = true génère un nouveau mot de passe.
 *
 * @return array{email:string, password:?string, created:bool}  password n'est renseigné que s'il vient d'être (re)défini
 */
function gestion_sync(int $hotelId, bool|string $resetPassword = false): array
{
    $pdo = db();
    $g = gestion_db();

    $st = $pdo->prepare('SELECT * FROM hotels WHERE id=?');
    $st->execute([$hotelId]);
    $h = $st->fetch();
    if (!$h) {
        throw new RuntimeException('Hôtel introuvable.');
    }

    // réservation automatique : un hôtel sans aucune offre reçoit une chambre standard par défaut (modifiable dans « Catalogues »)
    $nbCat = $pdo->prepare('SELECT COUNT(*) FROM catalogues WHERE hotel_id=?');
    $nbCat->execute([$hotelId]);
    if (!(int) $nbCat->fetchColumn()) {
        $pdo->prepare('INSERT INTO catalogues (titre,description,prix,capacite,nb_chambres,disponible,hotel_id) VALUES (?,?,?,?,?,?,?)')
            ->execute(['Chambre standard', 'Chambre confortable (offre par défaut, à personnaliser).', 25000, 2, 5, 1, $hotelId]);
    }

    // identifiant de connexion : l'e-mail de l'hôtel (ou un e-mail généré s'il n'en a pas)
    $email = strtolower(trim((string) $h['email']));
    if ($email === '') {
        $email = 'hotel' . $hotelId . '@gestion.local';
        $pdo->prepare('UPDATE hotels SET email=? WHERE id=?')->execute([$email, $hotelId]);
    }

    $now = date('Y-m-d H:i:s');
    $password = null;
    $created = false;

    $g->beginTransaction();
    try {
        // 1. hôtel (+ tenant)
        $lh = $g->prepare('SELECT * FROM hotels WHERE vitrine_id=?');
        $lh->execute([$hotelId]);
        $lh = $lh->fetch();
        if (!$lh) {
            $g->prepare('INSERT INTO tenants (name, created_at, updated_at) VALUES (?,?,?)')->execute([$h['nom'], $now, $now]);
            $tenantId = (int) $g->lastInsertId();
            $g->prepare('INSERT INTO hotels (tenant_id,name,city,currency,vitrine_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$tenantId, $h['nom'], $h['ville'], 'XOF', $hotelId, $now, $now]);
            $lhId = (int) $g->lastInsertId();
        } else {
            $tenantId = (int) $lh['tenant_id'];
            $lhId = (int) $lh['id'];
            $g->prepare('UPDATE hotels SET name=?, city=?, updated_at=? WHERE id=?')->execute([$h['nom'], $h['ville'], $now, $lhId]);
            $g->prepare('UPDATE tenants SET name=?, updated_at=? WHERE id=?')->execute([$h['nom'], $now, $tenantId]);
        }
        $pdo->prepare('UPDATE hotels SET gestion_hotel_id=? WHERE id=?')->execute([$lhId, $hotelId]);

        // le statut décidé par l'admin général commande l'accès de l'hôtel à son espace
        $status = gestion_access_state($h)['status'];
        $g->prepare("UPDATE hotels SET status=?, activated_at=IF(status='active', COALESCE(activated_at, ?), activated_at) WHERE id=?")
            ->execute([$status, $now, $lhId]);

        // 2. compte propriétaire
        $owner = $g->prepare("SELECT u.* FROM users u JOIN hotel_user hu ON hu.user_id=u.id WHERE hu.hotel_id=? AND hu.role='owner' ORDER BY u.id LIMIT 1");
        $owner->execute([$lhId]);
        $owner = $owner->fetch();

        $dup = $g->prepare('SELECT id FROM users WHERE email=? AND id<>?');
        $dup->execute([$email, $owner['id'] ?? 0]);
        if ($dup->fetchColumn()) {
            throw new RuntimeException("L'e-mail $email est déjà utilisé par un autre compte : changez l'e-mail de l'hôtel.");
        }

        if (!$owner) {
            $password = is_string($resetPassword) ? $resetPassword : gestion_password();
            $g->prepare('INSERT INTO users (tenant_id,name,email,email_verified_at,password,is_platform_admin,created_at,updated_at) VALUES (?,?,?,?,?,0,?,?)')
                ->execute([$tenantId, 'Gérant ' . $h['nom'], $email, $now, password_hash($password, PASSWORD_BCRYPT), $now, $now]);
            $uid = (int) $g->lastInsertId();
            $g->prepare("INSERT INTO hotel_user (hotel_id,user_id,role,created_at,updated_at) VALUES (?,?,'owner',?,?)")->execute([$lhId, $uid, $now, $now]);
            $created = true;
        } else {
            if ($resetPassword) {
                $password = is_string($resetPassword) ? $resetPassword : gestion_password();
                $g->prepare('UPDATE users SET password=?, updated_at=? WHERE id=?')->execute([password_hash($password, PASSWORD_BCRYPT), $now, $owner['id']]);
            }
            if ($owner['email'] !== $email) {
                $g->prepare('UPDATE users SET email=?, updated_at=? WHERE id=?')->execute([$email, $now, $owner['id']]);
            }
        }

        // 3. catalogue -> types de chambres + chambres
        $cats = $pdo->prepare('SELECT * FROM catalogues WHERE hotel_id=?');
        $cats->execute([$hotelId]);
        $nextNum = (int) $g->query("SELECT COALESCE(MAX(CAST(number AS UNSIGNED)),100) FROM rooms WHERE hotel_id=$lhId")->fetchColumn();
        foreach ($cats->fetchAll() as $c) {
            $rt = $g->prepare('SELECT id FROM room_types WHERE vitrine_catalogue_id=? AND hotel_id=?');
            $rt->execute([$c['id'], $lhId]);
            $rtId = $rt->fetchColumn();
            if ($rtId) {
                $g->prepare('UPDATE room_types SET name=?, base_price=?, updated_at=? WHERE id=?')->execute([$c['titre'], $c['prix'], $now, $rtId]);
            } else {
                $g->prepare('INSERT INTO room_types (tenant_id,hotel_id,name,base_price,vitrine_catalogue_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$tenantId, $lhId, $c['titre'], $c['prix'], $c['id'], $now, $now]);
                $rtId = (int) $g->lastInsertId();
            }
            $have = $g->prepare('SELECT COUNT(*) FROM rooms WHERE room_type_id=?');
            $have->execute([$rtId]);
            for ($n = (int) $have->fetchColumn(); $n < (int) $c['nb_chambres']; $n++) {
                $nextNum++;
                $g->prepare("INSERT INTO rooms (tenant_id,hotel_id,room_type_id,number,status,created_at,updated_at) VALUES (?,?,?,?,'available',?,?)")
                    ->execute([$tenantId, $lhId, $rtId, (string) $nextNum, $now, $now]);
            }
        }
        $g->commit();
    } catch (Throwable $ex) {
        $g->rollBack();
        throw $ex instanceof RuntimeException ? $ex : new RuntimeException('Synchronisation avec gestion-hotel impossible : ' . $ex->getMessage());
    }

    if ($password !== null) { // copie consultable par l'administrateur générale
        $pdo->prepare('UPDATE hotels SET acces_mdp=? WHERE id=?')->execute([$password, $hotelId]);
    }

    return ['email' => $email, 'password' => $password, 'created' => $created];
}

/** Supprime l'espace de gestion lié à un hôtel (hôtel, comptes, chambres, réservations en cascade). */
function gestion_delete(int $hotelId): void
{
    $g = gestion_db();
    $lh = $g->prepare('SELECT id, tenant_id FROM hotels WHERE vitrine_id=?');
    $lh->execute([$hotelId]);
    if (!($lh = $lh->fetch())) {
        return;
    }
    $g->beginTransaction();
    $g->prepare('DELETE FROM users WHERE tenant_id=?')->execute([$lh['tenant_id']]);
    $g->prepare('DELETE FROM tenants WHERE id=?')->execute([$lh['tenant_id']]);
    $g->commit();
}

/** Message affichable avec les identifiants (à montrer une seule fois). */
function gestion_credentials_message(array $r): string
{
    return "Accès de l'hôtel — adresse : " . GESTION_LOGIN_URL . ' · e-mail : ' . $r['email'] . ' · mot de passe : ' . $r['password'] . " (à lui communiquer, il ne sera plus affiché).";
}

/** Secret partagé avec gestion-hotel (variable BRIDGE_SECRET de son fichier .env). */
function gestion_bridge_secret(): string
{
    if (($v = getenv('BRIDGE_SECRET')) !== false && $v !== '') {
        return $v;
    }
    $env = dirname(__DIR__, 2) . '/gestion-hotel/.env';
    if (is_readable($env) && preg_match('/^BRIDGE_SECRET=(.+)$/m', (string) file_get_contents($env), $m)) {
        return trim($m[1], " \t\n\r\0\x0B\"'");
    }
    return '';
}

/**
 * Demande à gestion-hotel d'envoyer l'e-mail automatique à l'hôtel.
 *   $event = 'activated'      : l'hôtel vient d'être activé (e-mail de bienvenue + accès)
 *   $event = 'password_reset' : nouveaux accès
 * Ne bloque jamais l'administration : retourne [envoyé ?, message].
 *
 * @return array{0:bool,1:string}
 */
function gestion_notify(int $hotelId, string $event, ?string $password = null): array
{
    $secret = gestion_bridge_secret();
    if ($secret === '') {
        return [false, 'secret BRIDGE_SECRET introuvable dans gestion-hotel/.env'];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\nX-Bridge-Secret: $secret\r\n",
        'content' => json_encode(['vitrine_id' => $hotelId, 'event' => $event, 'password' => $password]),
        'timeout' => 8,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents(GESTION_BASE_URL . '/api/internal/hotel-access', false, $ctx);
    if ($res === false) {
        return [false, 'le site de gestion ne répond pas (lancez « php artisan serve » dans gestion-hotel)'];
    }
    $j = json_decode($res, true);
    return [($j['sent'] ?? false) === true, (string) ($j['message'] ?? 'réponse inattendue du site de gestion')];
}

/** Texte à ajouter au message affiché à l'administrateur après l'envoi de l'e-mail. */
function gestion_mail_note(int $hotelId, string $event, ?string $password): string
{
    [$ok, $msg] = gestion_notify($hotelId, $event, $password);
    return $ok ? ' ✉ ' . $msg : ' ⚠ E-mail non envoyé : ' . $msg . ' — communiquez les accès vous-même.';
}
