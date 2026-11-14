<?php
require __DIR__ . '/app.php';
require_login();

$n = fn(string $sql) => (int) db()->query($sql)->fetchColumn();
$stats = [
    ['Hôtels actifs', $n("SELECT COUNT(*) FROM hotels WHERE statut='actif'"), 'success'],
    ['Hôtels en publicité (payée)', $n('SELECT COUNT(*) FROM hotels WHERE pub_fin >= CURDATE()'), 'info'],
    ['Inscriptions en attente', $n("SELECT COUNT(*) FROM hotels WHERE statut='en_attente'"), 'warning'],
    ['Articles de catalogue', $n('SELECT COUNT(*) FROM catalogues'), 'primary'],
    ['Réservations en attente', $n("SELECT COUNT(*) FROM reservations WHERE statut='en_attente'"), 'danger'],
];
$revenu = (int) db()->query('SELECT COALESCE(SUM(montant),0) FROM paiements_pub')->fetchColumn();
$stats[] = ['Revenus publicité', money($revenu), 'dark'];
$derniers = db()->query('SELECT r.*, h.nom AS hotel, c.titre FROM reservations r JOIN hotels h ON h.id=r.hotel_id JOIN catalogues c ON c.id=r.catalogue_id ORDER BY r.id DESC LIMIT 8')->fetchAll();

header_html('Tableau de bord', 'index.php');
?>
<h3 class="mb-4">Tableau de bord</h3>
<div class="row g-3 mb-4">
<?php foreach ($stats as [$label, $val, $color]): ?>
  <div class="col-md"><div class="card border-<?= $color ?>"><div class="card-body"><div class="text-muted"><?= e($label) ?></div><div class="fs-2 fw-bold"><?= $val ?></div></div></div></div>
<?php endforeach; ?>
</div>
<h5>Dernières réservations</h5>
<table class="table table-sm table-striped">
  <tr><th>#</th><th>Client</th><th>Hôtel</th><th>Offre</th><th>Séjour</th><th>Total</th><th>Statut</th></tr>
  <?php foreach ($derniers as $r): ?>
  <tr><td><?= $r['id'] ?></td><td><?= e($r['client_nom']) ?></td><td><?= e($r['hotel']) ?></td><td><?= e($r['titre']) ?></td>
    <td><?= e($r['arrivee']) ?> → <?= e($r['depart']) ?></td><td><?= money($r['total']) ?></td><td><?= badge(STATUTS_RESA, $r['statut']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php footer_html();
