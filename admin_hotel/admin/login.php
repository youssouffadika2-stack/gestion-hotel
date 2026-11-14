<?php
require __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT * FROM admins WHERE email = ?');
    $st->execute([trim($_POST['email'] ?? '')]);
    $a = $st->fetch();
    if ($a && password_verify($_POST['mot_de_passe'] ?? '', $a['mot_de_passe'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = ['id' => $a['id'], 'nom' => $a['nom']];
        redirect('index.php');
    }
    flash('Identifiants incorrects.', 'danger');
    redirect('login.php');
}
header_html('Connexion');
?>
<div class="row justify-content-center"><div class="col-md-4">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Administrateur général</h4>
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Mot de passe</label><input type="password" name="mot_de_passe" class="form-control" required></div>
      <button class="btn btn-dark w-100">Se connecter</button>
    </form>
  </div></div>
</div></div>
<?php footer_html();
