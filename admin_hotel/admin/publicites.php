<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['hotel_id'] ?? 0);
    $st = $pdo->prepare('SELECT pub_fin FROM hotels WHERE id=?');
    $st->execute([$id]);
    $hotel = $st->fetch();
    if (!$hotel) {
        flash('Hôtel introuvable.', 'danger');
        redirect('publicites.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'paiement') {
        $montant = (int) ($_POST['montant'] ?? 0);
        $jours = (int) ($_POST['jours'] ?? 0);
        if ($montant <= 0 || $jours <= 0 || $jours > 3650) {
            flash('Montant et durée (en jours) doivent être positifs.', 'danger');
            redirect('publicites.php');
        }
        // Si la pub est encore active, la nouvelle période s'ajoute à la suite
        $today = date('Y-m-d');
        $debut = ($hotel['pub_fin'] && $hotel['pub_fin'] >= $today) ? date('Y-m-d', strtotime($hotel['pub_fin'] . ' +1 day')) : $today;
        $fin = date('Y-m-d', strtotime($debut . ' +' . ($jours - 1) . ' days'));
        $mode = substr(trim($_POST['mode'] ?? ''), 0, 40);
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO paiements_pub (hotel_id,montant,jours,mode,debut,fin) VALUES (?,?,?,?,?,?)')->execute([$id, $montant, $jours, $mode, $debut, $fin]);
        $pdo->prepare("UPDATE hotels SET pub_fin=?, statut=IF(statut='en_attente','actif',statut) WHERE id=?")->execute([$fin, $id]);
        $pdo->commit();
        gestion_sync($id); // l'hôtel est activé automatiquement dans son espace
        $dispo = $pdo->prepare('SELECT COUNT(*) FROM catalogues WHERE hotel_id=? AND disponible=1');
        $dispo->execute([$id]);
        if ($dispo->fetchColumn()) {
            flash("Paiement enregistré : publicité active jusqu'au " . $fin . '.');
        } else {
            flash("Paiement enregistré (publicité jusqu'au " . $fin . "), MAIS cet hôtel n'a aucune offre disponible dans son catalogue : le formulaire de réservation n'apparaîtra pas. Ajoutez une chambre/offre dans « Catalogues ».", 'warning');
        }
    } elseif ($action === 'arreter') {
        // fin immédiate de la période payée : l'hôtel disparaît de l'accueil et son accès à la gestion est suspendu
        $pdo->prepare('UPDATE hotels SET pub_fin=DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=?')->execute([$id]);
        gestion_sync($id);
        flash("Publicité arrêtée : l'hôtel disparaît de l'accueil et son accès à la gestion est suspendu (il voit automatiquement le message l'invitant à vous appeler).");
    }
    redirect('publicites.php');
}

$hotels = $pdo->query('SELECT h.id,h.nom,h.ville,h.statut,h.pub_fin,(SELECT COUNT(*) FROM catalogues c WHERE c.hotel_id=h.id AND c.disponible=1) AS nb_dispo, (SELECT COALESCE(SUM(montant),0) FROM paiements_pub p WHERE p.hotel_id=h.id) AS total_paye FROM hotels h ORDER BY h.pub_fin IS NULL, h.pub_fin DESC, h.nom')->fetchAll();
$histo = $pdo->query('SELECT p.*, h.nom FROM paiements_pub p JOIN hotels h ON h.id=p.hotel_id ORDER BY p.id DESC LIMIT 15')->fetchAll();
$today = date('Y-m-d');

header_html('Publicités', 'publicites.php');
?>
<h3 class="mb-1">Publicités sur la page d'accueil</h3>
<p class="text-muted">Un hôtel n'est visible sur la page d'accueil (et donc réservable) que pendant la période de publicité payée. À l'échéance, il disparaît automatiquement.</p>
<table class="table table-striped align-middle">
  <tr><th>Hôtel</th><th>Publicité</th><th>Total payé</th><th style="width:520px">Enregistrer un paiement</th></tr>
  <?php foreach ($hotels as $h):
      $actif = $h['pub_fin'] && $h['pub_fin'] >= $today; ?>
  <tr>
    <td><strong><?= e($h['nom']) ?></strong><br><small class="text-muted"><?= e($h['ville']) ?> · <?= badge(STATUTS_HOTEL, $h['statut']) ?></small>
      <?php if (!$h['nb_dispo']): ?><br><a href="catalogues.php?hotel=<?= $h['id'] ?>&new=1" class="badge text-bg-danger text-decoration-none">Catalogue vide : réservation impossible, ajouter une offre</a><?php endif; ?></td>
    <td><?php if ($actif): ?><span class="badge text-bg-success">Active</span><br><small>jusqu'au <?= e($h['pub_fin']) ?></small>
        <?php elseif ($h['pub_fin']): ?><span class="badge text-bg-danger">Expirée</span><br><small><?= e($h['pub_fin']) ?></small>
        <?php else: ?><span class="badge text-bg-secondary">Aucune</span><?php endif; ?></td>
    <td><?= money($h['total_paye']) ?></td>
    <td>
      <form method="post" class="row g-1"><?= csrf_field() ?><input type="hidden" name="action" value="paiement"><input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
        <div class="col-4"><input type="number" min="1" name="montant" class="form-control form-control-sm" placeholder="Montant FCFA" required></div>
        <div class="col-3"><select name="jours" class="form-select form-select-sm"><option value="30">30 jours</option><option value="90">90 jours</option><option value="180">180 jours</option><option value="365">1 an</option><option value="7">7 jours</option></select></div>
        <div class="col-3"><select name="mode" class="form-select form-select-sm"><option>Espèces</option><option>Orange Money</option><option>Virement</option><option>Autre</option></select></div>
        <div class="col-2"><button class="btn btn-sm btn-success w-100">Payer</button></div>
      </form>
      <?php if ($actif): ?>
      <form method="post" class="mt-1" onsubmit="return confirm('Arrêter la publicité de cet hôtel ?')"><?= csrf_field() ?><input type="hidden" name="action" value="arreter"><input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
        <button class="btn btn-sm btn-link text-danger p-0">Arrêter la publicité</button></form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h5 class="mt-4">Derniers paiements</h5>
<table class="table table-sm">
  <tr><th>Date</th><th>Hôtel</th><th>Montant</th><th>Mode</th><th>Période</th></tr>
  <?php foreach ($histo as $p): ?>
  <tr><td><?= e(substr($p['cree_le'], 0, 10)) ?></td><td><?= e($p['nom']) ?></td><td><?= money($p['montant']) ?></td><td><?= e($p['mode']) ?></td><td><?= e($p['debut']) ?> → <?= e($p['fin']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$histo): ?><tr><td colspan="5" class="text-muted">Aucun paiement.</td></tr><?php endif; ?>
</table>
<?php footer_html();
