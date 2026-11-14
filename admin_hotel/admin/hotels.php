<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'save') {
            $nom = trim($_POST['nom'] ?? '');
            $ville = trim($_POST['ville'] ?? '');
            if ($nom === '' || $ville === '') {
                throw new RuntimeException('Nom et ville obligatoires.');
            }
            $statut = array_key_exists($_POST['statut'] ?? '', STATUTS_HOTEL) ? $_POST['statut'] : 'actif';
            $data = [$nom, $ville, trim($_POST['adresse'] ?? ''), trim($_POST['telephone'] ?? ''), trim($_POST['email'] ?? ''),
                max(1, min(5, (int) ($_POST['etoiles'] ?? 3))), trim($_POST['description'] ?? ''), trim($_POST['publicite'] ?? ''), $statut];
            $img = save_image($_FILES['image'] ?? []);
            $logo = save_image($_FILES['logo'] ?? []);
            if ($id) {
                foreach (['image' => $img, 'logo' => $logo] as $col => $new) {
                    if ($new) {
                        $old = $pdo->prepare("SELECT $col FROM hotels WHERE id=?");
                        $old->execute([$id]);
                        delete_image($old->fetchColumn() ?: null);
                        $pdo->prepare("UPDATE hotels SET $col=? WHERE id=?")->execute([$new, $id]);
                    }
                }
                $old = $pdo->prepare('SELECT statut FROM hotels WHERE id=?');
                $old->execute([$id]);
                $oldStatut = (string) $old->fetchColumn();
                $pdo->prepare('UPDATE hotels SET nom=?,ville=?,adresse=?,telephone=?,email=?,etoiles=?,description=?,publicite=?,statut=? WHERE id=?')
                    ->execute([...$data, $id]);
                // Première validation (en attente -> actif) : mot de passe généré et envoyé par e-mail. Une réactivation après suspension garde le même mot de passe.
                $activating = $statut === 'actif' && $oldStatut === 'en_attente';
                $r = gestion_sync($id, $activating);
                $note = $statut === 'actif' && ($activating || $r['password']) ? gestion_mail_note($id, $activating ? 'activated' : 'password_reset', $r['password']) : '';
                flash('Hôtel modifié.' . ($r['password'] ? ' ' . gestion_credentials_message($r) : '') . $note);
            } else {
                $pdo->prepare('INSERT INTO hotels (nom,ville,adresse,telephone,email,etoiles,description,publicite,statut,image,logo) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([...$data, $img, $logo]);
                $newId = (int) $pdo->lastInsertId();
                $r = gestion_sync($newId);
                $note = $statut === 'actif' ? gestion_mail_note($newId, 'activated', $r['password']) : '';
                flash('Hôtel ajouté. ' . gestion_credentials_message($r) . $note);
            }
        } elseif ($action === 'statut' && array_key_exists($_POST['statut'] ?? '', STATUTS_HOTEL)) {
            $old = $pdo->prepare('SELECT statut FROM hotels WHERE id=?');
            $old->execute([$id]);
            $oldStatut = (string) $old->fetchColumn();
            $pdo->prepare('UPDATE hotels SET statut=? WHERE id=?')->execute([$_POST['statut'], $id]);
            // Validation (passage à « actif ») : nouveau mot de passe généré et envoyé automatiquement par e-mail à l'hôtel.
            $activating = $_POST['statut'] === 'actif' && $oldStatut === 'en_attente';
            $r = gestion_sync($id, $activating);
            $note = $activating ? gestion_mail_note($id, 'activated', $r['password']) : '';
            flash('Statut mis à jour.' . ($r['password'] ? ' ' . gestion_credentials_message($r) : '') . $note);
        } elseif ($action === 'acces') {
            $r = gestion_sync($id, true);
            $st = $pdo->prepare('SELECT statut FROM hotels WHERE id=?');
            $st->execute([$id]);
            $note = $st->fetchColumn() === 'actif'
                ? gestion_mail_note($id, 'password_reset', $r['password'])
                : ' (L\'hôtel n\'est pas encore actif : il recevra ses accès par e-mail à l\'activation.)';
            flash(gestion_credentials_message($r) . $note, 'info');
        } elseif ($action === 'delete') {
            // supprime aussi les images des catalogues (les lignes partent en cascade)
            $imgs = $pdo->prepare('SELECT image FROM catalogues WHERE hotel_id=? UNION SELECT image FROM hotels WHERE id=? UNION SELECT logo FROM hotels WHERE id=? UNION SELECT fichier FROM photos WHERE hotel_id=?');
            $imgs->execute([$id, $id, $id, $id]);
            foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $f) {
                delete_image($f);
            }
            gestion_delete($id);
            $pdo->prepare('DELETE FROM hotels WHERE id=?')->execute([$id]);
            flash('Hôtel supprimé avec son catalogue et ses réservations.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'danger');
        redirect($action === 'save' ? 'hotels.php' . ($id ? '?edit=' . $id : '?new=1') : 'hotels.php');
    }
    redirect('hotels.php');
}

$form = isset($_GET['new']) || isset($_GET['edit']);
header_html('Hôtels', 'hotels.php');

if ($form) {
    $h = ['id' => 0, 'nom' => '', 'ville' => '', 'adresse' => '', 'telephone' => '', 'email' => '', 'etoiles' => 3, 'description' => '', 'publicite' => '', 'statut' => 'actif', 'image' => null, 'logo' => null];
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM hotels WHERE id=?');
        $st->execute([(int) $_GET['edit']]);
        $h = $st->fetch() ?: redirect('hotels.php');
    }
    ?>
<h3><?= $h['id'] ? 'Modifier' : 'Ajouter' ?> un hôtel</h3>
<form method="post" enctype="multipart/form-data" class="row g-3"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $h['id'] ?>">
  <div class="col-md-6"><label class="form-label">Nom *</label><input name="nom" class="form-control" required value="<?= e($h['nom']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Ville *</label><input name="ville" class="form-control" required value="<?= e($h['ville']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Étoiles</label><select name="etoiles" class="form-select">
    <?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= $i ?>" <?= $i == $h['etoiles'] ? 'selected' : '' ?>><?= $i ?> ★</option><?php endfor; ?></select></div>
  <div class="col-md-6"><label class="form-label">Adresse</label><input name="adresse" class="form-control" value="<?= e($h['adresse']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Téléphone</label><input name="telephone" class="form-control" value="<?= e($h['telephone']) ?>"></div>
  <div class="col-md-3"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="<?= e($h['email']) ?>"></div>
  <div class="col-12"><label class="form-label">Slogan publicitaire (affiché sur la page d'accueil)</label><input name="publicite" maxlength="255" class="form-control" value="<?= e($h['publicite']) ?>"></div>
  <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="4" class="form-control"><?= e($h['description']) ?></textarea></div>
  <div class="col-md-3"><label class="form-label">Logo de l'hôtel</label><input type="file" name="logo" accept="image/*" class="form-control">
    <?php if ($h['logo']): ?><img src="../uploads/<?= e($h['logo']) ?>" class="thumb mt-2" style="object-fit:contain"><?php endif; ?></div>
  <div class="col-md-3"><label class="form-label">Photo principale</label><input type="file" name="image" accept="image/*" class="form-control">
    <?php if ($h['image']): ?><img src="../uploads/<?= e($h['image']) ?>" class="thumb mt-2"><?php endif; ?></div>
  <div class="col-md-3"><label class="form-label">Statut</label><select name="statut" class="form-select">
    <?php foreach (STATUTS_HOTEL as $k => [$l]): ?><option value="<?= $k ?>" <?= $k === $h['statut'] ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-12"><button class="btn btn-dark">Enregistrer</button> <a href="hotels.php" class="btn btn-link">Annuler</a></div>
</form>
<?php
    footer_html();
    exit;
}

$hotels = $pdo->query('SELECT h.*, (SELECT COUNT(*) FROM catalogues c WHERE c.hotel_id=h.id) AS nb_cat, (SELECT COUNT(*) FROM catalogues c WHERE c.hotel_id=h.id AND c.disponible=1) AS nb_dispo,
  (SELECT COUNT(*) FROM reservations r WHERE r.hotel_id=h.id) AS nb_res FROM hotels h ORDER BY FIELD(h.statut,"en_attente","actif","suspendu"), h.id DESC')->fetchAll();
?>
<div class="d-flex justify-content-between mb-3"><h3>Hôtels inscrits (<?= count($hotels) ?>)</h3><a class="btn btn-dark" href="?new=1"><i class="bi bi-plus-lg"></i> Ajouter un hôtel</a></div>
<table class="table table-striped align-middle">
  <tr><th></th><th>Hôtel</th><th>Ville</th><th>Contact</th><th>Catalogue</th><th>Réserv.</th><th>Statut</th><th>Publicité (accueil)</th><th>Accès gestion</th><th style="width:300px">Actions</th></tr>
  <?php foreach ($hotels as $h): ?>
  <tr>
    <td><img class="thumb" style="object-fit:contain" src="<?= $h['logo'] ? '../uploads/' . e($h['logo']) : 'https://placehold.co/60x45?text=Logo' ?>"></td>
    <td><strong><?= e($h['nom']) ?></strong><br><small class="text-warning"><?= str_repeat('★', (int) $h['etoiles']) ?></small></td>
    <td><?= e($h['ville']) ?></td>
    <td><small><?= e($h['telephone']) ?><br><?= e($h['email']) ?></small></td>
    <td><a href="catalogues.php?hotel=<?= $h['id'] ?>"><?= $h['nb_cat'] ?> article(s)</a>
      <?php if (!$h['nb_dispo']): ?><br><span class="badge text-bg-danger" title="Sans offre disponible, le client ne peut pas réserver">Pas de réservation possible</span><?php endif; ?><br><a href="photos.php?hotel=<?= $h['id'] ?>">Photos</a></td>
    <td><?= $h['nb_res'] ?></td>
    <td><?= badge(STATUTS_HOTEL, $h['statut']) ?></td>
    <td><?php if ($h['pub_fin'] && $h['pub_fin'] >= date('Y-m-d')): ?><span class="badge text-bg-success">Visible</span><br><small>jusqu'au <?= e($h['pub_fin']) ?></small>
      <?php else: ?><span class="badge text-bg-danger">Non visible</span><br><a href="publicites.php"><small>Enregistrer le paiement</small></a><?php endif; ?></td>
    <td><?php [$acc] = [gestion_access_state($h)]; $accLabels = ['active' => ['Accès actif', 'success'], 'pending' => [$acc['reason'] === 'payment' ? 'En attente de paiement' : 'En attente de validation', 'warning'], 'suspended' => [$acc['reason'] === 'expired' ? 'Suspendu : abonnement expiré' : 'Suspendu', 'danger']]; ?>
      <span class="badge text-bg-<?= $accLabels[$acc['status']][1] ?>"><?= e($accLabels[$acc['status']][0]) ?></span><br>
      <small><?= $h['gestion_hotel_id'] ? e($h['email']) : '<span class="text-muted">pas de compte</span>' ?></small><br>
      <a class="btn btn-link btn-sm p-0" href="acces.php?hotel=<?= $h['id'] ?>"><?= $h['gestion_hotel_id'] ? "Définir e-mail / mot de passe" : 'Créer le compte' ?></a></td>
    <td>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="statut"><input type="hidden" name="id" value="<?= $h['id'] ?>">
        <?php if ($h['statut'] !== 'actif'): ?><button name="statut" value="actif" class="btn btn-sm btn-success">Valider</button>
        <?php else: ?><button name="statut" value="suspendu" class="btn btn-sm btn-secondary">Suspendre</button><?php endif; ?></form>
      <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $h['id'] ?>">Modifier</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Supprimer cet hôtel, son catalogue et ses réservations ?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $h['id'] ?>"><button class="btn btn-sm btn-outline-danger">Supprimer</button></form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php footer_html();
