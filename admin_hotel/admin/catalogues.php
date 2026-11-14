<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

$hotels = $pdo->query('SELECT id, nom FROM hotels ORDER BY nom')->fetchAll(PDO::FETCH_KEY_PAIR);
$hotelId = (int) ($_GET['hotel'] ?? $_POST['hotel_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'save') {
            $titre = trim($_POST['titre'] ?? '');
            if ($titre === '' || !isset($hotels[$hotelId])) {
                throw new RuntimeException('Hôtel et titre obligatoires.');
            }
            $data = [$titre, trim($_POST['description'] ?? ''), max(0, (int) ($_POST['prix'] ?? 0)),
                max(1, (int) ($_POST['capacite'] ?? 2)), max(1, min(200, (int) ($_POST['nb_chambres'] ?? 3))), isset($_POST['disponible']) ? 1 : 0];
            $img = save_image($_FILES['image'] ?? []);
            if ($id) {
                if ($img) {
                    $old = $pdo->prepare('SELECT image FROM catalogues WHERE id=?');
                    $old->execute([$id]);
                    delete_image($old->fetchColumn() ?: null);
                    $pdo->prepare('UPDATE catalogues SET image=? WHERE id=?')->execute([$img, $id]);
                }
                $pdo->prepare('UPDATE catalogues SET titre=?,description=?,prix=?,capacite=?,nb_chambres=?,disponible=?,hotel_id=? WHERE id=?')
                    ->execute([...$data, $hotelId, $id]);
                gestion_sync($hotelId);
                flash('Article modifié.');
            } else {
                $pdo->prepare('INSERT INTO catalogues (titre,description,prix,capacite,nb_chambres,disponible,hotel_id,image) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([...$data, $hotelId, $img]);
                gestion_sync($hotelId);
                flash("Article ajouté au catalogue (chambres créées dans l'espace de l'hôtel).");
            }
        } elseif ($action === 'delete') {
            $old = $pdo->prepare('SELECT image FROM catalogues WHERE id=?');
            $old->execute([$id]);
            delete_image($old->fetchColumn() ?: null);
            $pdo->prepare('DELETE FROM catalogues WHERE id=?')->execute([$id]);
            gestion_db()->prepare('UPDATE room_types SET vitrine_catalogue_id=NULL WHERE vitrine_catalogue_id=?')->execute([$id]);
            flash('Article supprimé.');
        }
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'danger');
        redirect('catalogues.php?hotel=' . $hotelId . ($id ? '&edit=' . $id : '&new=1'));
    }
    redirect('catalogues.php?hotel=' . $hotelId);
}

header_html('Catalogues', 'catalogues.php');
?>
<h3 class="mb-3">Catalogues des hôtels</h3>
<form method="get" class="row g-2 mb-4">
  <div class="col-md-4"><select name="hotel" class="form-select" onchange="this.form.submit()">
    <option value="">— Choisir un hôtel —</option>
    <?php foreach ($hotels as $k => $nom): ?><option value="<?= $k ?>" <?= $k === $hotelId ? 'selected' : '' ?>><?= e($nom) ?></option><?php endforeach; ?></select></div>
  <?php if ($hotelId && isset($hotels[$hotelId])): ?><div class="col-auto"><a class="btn btn-dark" href="?hotel=<?= $hotelId ?>&new=1"><i class="bi bi-plus-lg"></i> Ajouter un article</a></div><?php endif; ?>
</form>
<?php
if (!$hotelId || !isset($hotels[$hotelId])) {
    echo '<p class="text-muted">Sélectionnez un hôtel pour gérer son catalogue (chambres, suites, offres…).</p>';
    footer_html();
    exit;
}

if (isset($_GET['new']) || isset($_GET['edit'])) {
    $c = ['id' => 0, 'titre' => '', 'description' => '', 'prix' => '', 'capacite' => 2, 'nb_chambres' => 3, 'disponible' => 1, 'image' => null];
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM catalogues WHERE id=? AND hotel_id=?');
        $st->execute([(int) $_GET['edit'], $hotelId]);
        $c = $st->fetch() ?: redirect('catalogues.php?hotel=' . $hotelId);
    }
    ?>
<h5><?= $c['id'] ? 'Modifier' : 'Nouvel' ?> article — <?= e($hotels[$hotelId]) ?></h5>
<form method="post" enctype="multipart/form-data" class="row g-3"><?= csrf_field() ?>
  <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="hotel_id" value="<?= $hotelId ?>">
  <div class="col-md-6"><label class="form-label">Titre * (ex : Chambre Deluxe)</label><input name="titre" class="form-control" required value="<?= e($c['titre']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Prix / nuit (FCFA)</label><input type="number" min="0" name="prix" class="form-control" required value="<?= e((string) $c['prix']) ?>"></div>
  <div class="col-md-2"><label class="form-label">Capacité (pers.)</label><input type="number" min="1" name="capacite" class="form-control" value="<?= $c['capacite'] ?>"></div>
  <div class="col-md-1"><label class="form-label" title="Nombre de chambres de ce type">Nb ch.</label><input type="number" min="1" max="200" name="nb_chambres" class="form-control" value="<?= $c['nb_chambres'] ?? 3 ?>"></div>
  <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="3" class="form-control"><?= e($c['description']) ?></textarea></div>
  <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="image" accept="image/*" class="form-control">
    <?php if ($c['image']): ?><img src="../uploads/<?= e($c['image']) ?>" class="thumb mt-2"><?php endif; ?></div>
  <div class="col-md-3 pt-4"><div class="form-check"><input type="checkbox" class="form-check-input" name="disponible" id="d" <?= $c['disponible'] ? 'checked' : '' ?>><label for="d" class="form-check-label">Disponible à la réservation</label></div></div>
  <div class="col-12"><button class="btn btn-dark">Enregistrer</button> <a href="?hotel=<?= $hotelId ?>" class="btn btn-link">Annuler</a></div>
</form>
<?php
    footer_html();
    exit;
}

$st = $pdo->prepare('SELECT * FROM catalogues WHERE hotel_id=? ORDER BY id DESC');
$st->execute([$hotelId]);
$items = $st->fetchAll();
?>
<table class="table table-striped align-middle">
  <tr><th></th><th>Titre</th><th>Prix / nuit</th><th>Pers.</th><th>Ch.</th><th>Dispo</th><th style="width:200px">Actions</th></tr>
  <?php foreach ($items as $c): ?>
  <tr>
    <td><img class="thumb" src="<?= $c['image'] ? '../uploads/' . e($c['image']) : 'https://placehold.co/60x45' ?>"></td>
    <td><strong><?= e($c['titre']) ?></strong><br><small class="text-muted"><?= e(mb_strimwidth($c['description'] ?? '', 0, 80, '…')) ?></small></td>
    <td><?= money($c['prix']) ?></td><td><?= $c['capacite'] ?></td><td><?= $c['nb_chambres'] ?></td>
    <td><?= $c['disponible'] ? '<span class="badge text-bg-success">Oui</span>' : '<span class="badge text-bg-secondary">Non</span>' ?></td>
    <td><a class="btn btn-sm btn-outline-primary" href="?hotel=<?= $hotelId ?>&edit=<?= $c['id'] ?>">Modifier</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Supprimer cet article ?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="hotel_id" value="<?= $hotelId ?>">
        <button class="btn btn-sm btn-outline-danger">Supprimer</button></form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="7" class="text-muted">Catalogue vide.</td></tr><?php endif; ?>
</table>
<?php footer_html();
