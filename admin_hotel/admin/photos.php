<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

$hotels = $pdo->query('SELECT id, nom FROM hotels ORDER BY nom')->fetchAll(PDO::FETCH_KEY_PAIR);
$hotelId = (int) ($_GET['hotel'] ?? $_POST['hotel_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($hotels[$hotelId])) {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        // normalise $_FILES['photos'] (envoi multiple) en une liste de fichiers
        $f = $_FILES['photos'] ?? ['name' => []];
        $ok = 0;
        $err = null;
        foreach (array_keys((array) $f['name']) as $i) {
            try {
                $name = save_image(['error' => $f['error'][$i], 'size' => $f['size'][$i], 'tmp_name' => $f['tmp_name'][$i]]);
                if ($name) {
                    $pdo->prepare('INSERT INTO photos (hotel_id,fichier,legende) VALUES (?,?,?)')
                        ->execute([$hotelId, $name, substr(trim($_POST['legende'] ?? ''), 0, 150)]);
                    $ok++;
                }
            } catch (RuntimeException $ex) {
                $err = $ex->getMessage();
            }
        }
        if ($ok) {
            flash($ok . ' photo(s) ajoutée(s).' . ($err ? ' Certaines ont été refusées : ' . $err : ''), $err ? 'warning' : 'success');
        } else {
            flash($err ?? 'Aucune photo sélectionnée.', 'danger');
        }
    } elseif ($action === 'legende') {
        $pdo->prepare('UPDATE photos SET legende=? WHERE id=? AND hotel_id=?')
            ->execute([substr(trim($_POST['legende'] ?? ''), 0, 150), (int) $_POST['id'], $hotelId]);
        flash('Légende modifiée.');
    } elseif ($action === 'principale') {
        // utilise cette photo comme photo principale de l'hôtel (affichée sur la page d'accueil)
        $st = $pdo->prepare('SELECT fichier FROM photos WHERE id=? AND hotel_id=?');
        $st->execute([(int) $_POST['id'], $hotelId]);
        if ($fichier = $st->fetchColumn()) {
            $cur = $pdo->prepare('SELECT image FROM hotels WHERE id=?');
            $cur->execute([$hotelId]);
            $old = $cur->fetchColumn();
            // l'ancienne photo principale n'est supprimée que si elle n'est pas dans la galerie
            $inGallery = $pdo->prepare('SELECT COUNT(*) FROM photos WHERE fichier=?');
            $inGallery->execute([$old]);
            if ($old && !$inGallery->fetchColumn()) {
                delete_image($old);
            }
            $pdo->prepare('UPDATE hotels SET image=? WHERE id=?')->execute([$fichier, $hotelId]);
            flash('Photo principale mise à jour.');
        }
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT fichier FROM photos WHERE id=? AND hotel_id=?');
        $st->execute([(int) $_POST['id'], $hotelId]);
        if ($fichier = $st->fetchColumn()) {
            $pdo->prepare('DELETE FROM photos WHERE id=?')->execute([(int) $_POST['id']]);
            $used = $pdo->prepare('SELECT (SELECT COUNT(*) FROM hotels WHERE image=? OR logo=?) + (SELECT COUNT(*) FROM catalogues WHERE image=?)');
            $used->execute([$fichier, $fichier, $fichier]);
            if (!$used->fetchColumn()) {
                delete_image($fichier);
            }
            flash('Photo supprimée.');
        }
    }
    redirect('photos.php?hotel=' . $hotelId);
}

header_html('Photos', 'photos.php');
?>
<h3 class="mb-3">Galerie de photos des hôtels</h3>
<form method="get" class="row g-2 mb-4">
  <div class="col-md-4"><select name="hotel" class="form-select" onchange="this.form.submit()">
    <option value="">— Choisir un hôtel —</option>
    <?php foreach ($hotels as $k => $nom): ?><option value="<?= $k ?>" <?= $k === $hotelId ? 'selected' : '' ?>><?= e($nom) ?></option><?php endforeach; ?></select></div>
</form>
<?php
if (!isset($hotels[$hotelId])) {
    echo '<p class="text-muted">Sélectionnez un hôtel pour gérer sa galerie : chambres, restaurant, piscine, façade… Ces photos serviront pour les publicités.</p>';
    footer_html();
    exit;
}
$st = $pdo->prepare('SELECT * FROM photos WHERE hotel_id=? ORDER BY id DESC');
$st->execute([$hotelId]);
$photos = $st->fetchAll();
$cur = $pdo->prepare('SELECT image FROM hotels WHERE id=?');
$cur->execute([$hotelId]);
$principale = $cur->fetchColumn();
?>
<form method="post" enctype="multipart/form-data" class="row g-2 mb-4 card card-body"><?= csrf_field() ?>
  <input type="hidden" name="action" value="upload"><input type="hidden" name="hotel_id" value="<?= $hotelId ?>">
  <div class="col-md-5"><label class="form-label">Ajouter des photos (plusieurs possibles)</label><input type="file" name="photos[]" multiple accept="image/*" class="form-control" required></div>
  <div class="col-md-5"><label class="form-label">Légende (optionnel)</label><input name="legende" maxlength="150" class="form-control" placeholder="ex : Piscine, Chambre Deluxe"></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-dark w-100">Téléverser</button></div>
</form>

<div class="row g-3">
<?php foreach ($photos as $p): ?>
  <div class="col-6 col-md-3"><div class="card h-100">
    <img src="../uploads/<?= e($p['fichier']) ?>" class="card-img-top" style="height:160px;object-fit:cover" alt="">
    <div class="card-body p-2">
      <?php if ($p['fichier'] === $principale): ?><span class="badge text-bg-success mb-1">Photo principale</span><?php endif; ?>
      <form method="post" class="input-group input-group-sm mb-2"><?= csrf_field() ?>
        <input type="hidden" name="hotel_id" value="<?= $hotelId ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="legende">
        <input name="legende" class="form-control" value="<?= e($p['legende']) ?>" placeholder="Légende"><button class="btn btn-outline-secondary">OK</button></form>
      <form method="post" class="d-flex gap-1"><?= csrf_field() ?>
        <input type="hidden" name="hotel_id" value="<?= $hotelId ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button name="action" value="principale" class="btn btn-sm btn-outline-primary flex-fill">Principale</button>
        <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer cette photo ?')"><i class="bi bi-trash"></i></button></form>
    </div>
  </div></div>
<?php endforeach; ?>
<?php if (!$photos): ?><p class="text-muted">Aucune photo pour cet hôtel.</p><?php endif; ?>
</div>
<?php footer_html();
