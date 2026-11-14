<?php
require __DIR__ . '/app.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT mot_de_passe FROM admins WHERE id=?');
    $st->execute([$u['id']]);
    $hash = $st->fetchColumn();
    $new = $_POST['nouveau'] ?? '';
    if (!password_verify($_POST['actuel'] ?? '', (string) $hash)) {
        flash('Mot de passe actuel incorrect.', 'danger');
    } elseif (strlen($new) < 8 || $new !== ($_POST['confirmation'] ?? '')) {
        flash('Le nouveau mot de passe doit faire 8 caractères minimum et être confirmé à l\'identique.', 'danger');
    } else {
        db()->prepare('UPDATE admins SET mot_de_passe=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        flash('Mot de passe modifié.');
    }
    redirect('motdepasse.php');
}

header_html('Mot de passe', 'motdepasse.php');
?>
<h3 class="mb-3">Changer mon mot de passe</h3>
<form method="post" class="col-md-4"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Mot de passe actuel</label><input type="password" name="actuel" class="form-control" required></div>
  <div class="mb-3"><label class="form-label">Nouveau mot de passe (8 caractères min.)</label><input type="password" name="nouveau" class="form-control" minlength="8" required></div>
  <div class="mb-3"><label class="form-label">Confirmation</label><input type="password" name="confirmation" class="form-control" required></div>
  <button class="btn btn-dark">Modifier</button>
</form>
<?php footer_html();
