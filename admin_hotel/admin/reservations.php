<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT gestion_reservation_id FROM reservations WHERE id=?');
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row && ($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM reservations WHERE id=?')->execute([$id]);
        flash('Réservation retirée de la liste (elle reste dans l\'espace de l\'hôtel).');
    } elseif ($row && !$row['gestion_reservation_id'] && array_key_exists($_POST['statut'] ?? '', STATUTS_RESA)) {
        $pdo->prepare('UPDATE reservations SET statut=? WHERE id=?')->execute([$_POST['statut'], $id]);
        flash('Réservation mise à jour.');
    }
    redirect('reservations.php');
}

// Le statut réel est celui de l'espace de l'hôtel (gestion-hotel) quand la réservation y est liée
$sql = "SELECT r.*, h.nom AS hotel, c.titre,
  CASE WHEN lr.id IS NULL THEN r.statut
       WHEN lr.status = 'pending' THEN 'en_attente'
       WHEN lr.status = 'cancelled' THEN 'annulee'
       ELSE 'confirmee' END AS statut_reel,
  lr.code AS code_gestion
  FROM reservations r JOIN hotels h ON h.id=r.hotel_id JOIN catalogues c ON c.id=r.catalogue_id
  LEFT JOIN " . GESTION_DB . ".reservations lr ON lr.id = r.gestion_reservation_id
  ORDER BY r.id DESC";
$rows = $pdo->query($sql)->fetchAll();
header_html('Réservations', 'reservations.php');
?>
<h3 class="mb-1">Réservations (<?= count($rows) ?>)</h3>
<p class="text-muted">Chaque réservation arrive directement dans l'espace de l'hôtel choisi par le client ; c'est l'hôtel qui la confirme ou l'annule. Le statut ci-dessous est mis à jour automatiquement.</p>
<table class="table table-striped align-middle">
  <tr><th>#</th><th>Client</th><th>Hôtel / offre</th><th>Séjour</th><th>Pers.</th><th>Total</th><th>Statut</th><th>Actions</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= $r['id'] ?><?php if ($r['code_gestion']): ?><br><small class="text-muted"><?= e($r['code_gestion']) ?></small><?php endif; ?></td>
    <td><strong><?= e($r['client_nom']) ?></strong><br><small><?= e($r['client_telephone']) ?> · <?= e($r['client_email']) ?></small></td>
    <td><?= e($r['hotel']) ?><br><small class="text-muted"><?= e($r['titre']) ?></small></td>
    <td><?= e($r['arrivee']) ?> → <?= e($r['depart']) ?></td><td><?= $r['personnes'] ?></td><td><?= money($r['total']) ?></td>
    <td><?= badge(STATUTS_RESA, $r['statut_reel']) ?></td>
    <td>
      <?php if (!$r['gestion_reservation_id']): ?>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button name="statut" value="confirmee" class="btn btn-sm btn-success">Confirmer</button>
        <button name="statut" value="annulee" class="btn btn-sm btn-warning">Annuler</button></form>
      <?php endif; ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Retirer cette réservation de la liste ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">Aucune réservation.</td></tr><?php endif; ?>
</table>
<?php footer_html();
