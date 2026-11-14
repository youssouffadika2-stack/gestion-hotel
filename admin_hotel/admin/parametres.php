<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['entreprise', 'telephone', 'email'] as $cle) {
        $pdo->prepare('INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
            ->execute([$cle, substr(trim($_POST[$cle] ?? ''), 0, 255)]);
    }
    flash('Coordonnées enregistrées : elles sont affichées aux hôtels suspendus.');
    redirect('parametres.php');
}

$p = $pdo->query('SELECT cle, valeur FROM parametres')->fetchAll(PDO::FETCH_KEY_PAIR);
header_html('Mon entreprise', 'parametres.php');
?>
<h3 class="mb-1">Coordonnées de l'entreprise de gestion</h3>
<p class="text-muted">Quand un hôtel est suspendu ou que son abonnement expire, il voit automatiquement dans son espace un message l'invitant à vous appeler à ce numéro.</p>
<form method="post" class="col-md-5"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Nom de l'entreprise</label><input name="entreprise" class="form-control" value="<?= e($p['entreprise'] ?? '') ?>"></div>
  <div class="mb-3"><label class="form-label">Téléphone à appeler *</label><input name="telephone" class="form-control" required value="<?= e($p['telephone'] ?? '') ?>" placeholder="ex : +223 70 00 00 00"></div>
  <div class="mb-3"><label class="form-label">E-mail (optionnel)</label><input type="email" name="email" class="form-control" value="<?= e($p['email'] ?? '') ?>"></div>
  <button class="btn btn-dark">Enregistrer</button>
</form>
<?php footer_html();
