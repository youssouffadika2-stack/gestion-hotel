<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

$hotelId = (int) ($_GET['hotel'] ?? $_POST['hotel_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM hotels WHERE id=?');
$st->execute([$hotelId]);
$h = $st->fetch() ?: redirect('hotels.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail de connexion invalide.');
        }
        if (isset($_POST['generer'])) {
            $mdp = gestion_password();
        } elseif ($mdp !== '' && strlen($mdp) < 8) {
            throw new RuntimeException('Le mot de passe doit faire 8 caractères minimum.');
        }
        $pdo->prepare('UPDATE hotels SET email=? WHERE id=?')->execute([$email, $hotelId]);
        // mot de passe vide = on ne change que l'e-mail (sauf si le compte n'existe pas encore : il est alors généré)
        $r = gestion_sync($hotelId, $mdp !== '' ? $mdp : false);
        flash($r['password'] ? gestion_credentials_message($r) : 'E-mail de connexion mis à jour (mot de passe inchangé).', 'info');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'danger');
    }
    redirect('acces.php?hotel=' . $hotelId);
}

header_html('Accès de l\'hôtel', 'hotels.php');
?>
<a href="hotels.php" class="small">&larr; Retour aux hôtels</a>
<h3 class="mt-2">Accès à l'espace de gestion — <?= e($h['nom']) ?></h3>
<p class="text-muted">Définissez l'e-mail et le mot de passe que l'hôtel utilisera pour se connecter sur
  <strong><?= e(GESTION_LOGIN_URL) ?></strong>. Communiquez-les ensuite à l'hôtel.</p>
<?php if (!$h['gestion_hotel_id']): ?><div class="alert alert-warning">Le compte n'existe pas encore : il sera créé à l'enregistrement.</div><?php endif; ?>
<div class="card col-md-5 mb-3"><div class="card-body">
  <div class="small text-muted">Accès actuels à communiquer</div>
  <div>Adresse : <?= e(GESTION_LOGIN_URL) ?></div>
  <div>E-mail : <strong><?= e($h['email']) ?></strong></div>
  <div>Mot de passe : <?php if ($h['acces_mdp']): ?><strong id="mdp" style="font-family:monospace"><?= e($h['acces_mdp']) ?></strong>
    <div class="form-text">Si l'hôtel a changé lui-même son mot de passe, celui-ci n'est plus valable : définissez-en un nouveau ci-dessous.</div>
    <?php else: ?><em class="text-muted">non enregistré : définissez-en un nouveau ci-dessous</em><?php endif; ?></div>
</div></div>
<form method="post" class="col-md-5"><?= csrf_field() ?>
  <input type="hidden" name="hotel_id" value="<?= $hotelId ?>">
  <div class="mb-3"><label class="form-label">E-mail de connexion</label>
    <input type="email" name="email" class="form-control" required value="<?= e($h['email']) ?>"></div>
  <div class="mb-3"><label class="form-label">Mot de passe (8 caractères min.)</label>
    <input type="text" name="mot_de_passe" class="form-control" autocomplete="off" placeholder="Laisser vide pour ne pas le changer">
    <div class="form-text">Vous pouvez aussi cliquer sur « Générer » pour obtenir un mot de passe aléatoire.</div></div>
  <button class="btn btn-dark">Enregistrer l'accès</button>
  <button name="generer" value="1" class="btn btn-outline-secondary" onclick="return confirm('Générer un nouveau mot de passe ?')">Générer un mot de passe</button>
</form>
<?php footer_html();
