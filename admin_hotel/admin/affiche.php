<?php
require __DIR__ . '/app.php';
require_login();
$pdo = db();

$hotels = $pdo->query('SELECT id, nom FROM hotels ORDER BY nom')->fetchAll(PDO::FETCH_KEY_PAIR);
$hotelId = (int) ($_GET['hotel'] ?? 0);

header_html('Créer une publicité', 'affiche.php');
?>
<h3 class="mb-3">Créer une affiche publicitaire</h3>
<form method="get" class="row g-2 mb-4">
  <div class="col-md-4"><select name="hotel" class="form-select" onchange="this.form.submit()">
    <option value="">— Choisir un hôtel —</option>
    <?php foreach ($hotels as $k => $nom): ?><option value="<?= $k ?>" <?= $k === $hotelId ? 'selected' : '' ?>><?= e($nom) ?></option><?php endforeach; ?></select></div>
</form>
<?php
if (!isset($hotels[$hotelId])) {
    echo '<p class="text-muted">Choisissez un hôtel : l\'affiche est composée à partir de son logo, de sa galerie photos et de son slogan.</p>';
    footer_html();
    exit;
}
$st = $pdo->prepare('SELECT * FROM hotels WHERE id=?');
$st->execute([$hotelId]);
$h = $st->fetch();
$st = $pdo->prepare('SELECT fichier FROM photos WHERE hotel_id=? ORDER BY id DESC');
$st->execute([$hotelId]);
$photos = $st->fetchAll(PDO::FETCH_COLUMN);
if ($h['image'] && !in_array($h['image'], $photos, true)) {
    array_unshift($photos, $h['image']);
}
$st = $pdo->prepare('SELECT MIN(prix) FROM catalogues WHERE hotel_id=? AND disponible=1');
$st->execute([$hotelId]);
$prix = $st->fetchColumn();
?>
<style>
  #affiche { width:480px; max-width:100%; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.2); font-family:Arial,sans-serif; }
  #affiche .photo { height:300px; background:#ccc center/cover no-repeat; position:relative; }
  #affiche .logo { position:absolute; top:12px; left:12px; width:64px; height:64px; background:#fff; border-radius:12px; padding:4px; object-fit:contain; }
  #affiche .body { padding:18px 22px 22px; background:linear-gradient(135deg,#1f2d3d,#0d6efd); color:#fff; }
  #affiche h2 { margin:0; font-size:26px; } #affiche .slogan { font-style:italic; font-size:18px; margin:8px 0; color:#ffd666; }
  #affiche .prix { background:#ffc107; color:#000; display:inline-block; padding:6px 14px; border-radius:20px; font-weight:bold; margin-top:6px; }
  @media print { body * { visibility:hidden; } #affiche, #affiche * { visibility:visible; } #affiche { position:absolute; left:0; top:0; box-shadow:none; } }
</style>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="mb-3"><label class="form-label">Slogan</label>
      <input id="f-slogan" class="form-control" value="<?= e($h['publicite'] ?: 'Réservez votre séjour de rêve') ?>"></div>
    <div class="mb-3"><label class="form-label">Contact affiché</label>
      <input id="f-contact" class="form-control" value="<?= e($h['telephone']) ?>"></div>
    <label class="form-label">Photo de l'affiche</label>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php foreach ($photos as $i => $f): ?>
        <img src="../uploads/<?= e($f) ?>" class="thumb pick" data-src="../uploads/<?= e($f) ?>" style="width:80px;height:60px;cursor:pointer;border:3px solid <?= $i === 0 ? '#0d6efd' : 'transparent' ?>">
      <?php endforeach; ?>
      <?php if (!$photos): ?><span class="text-muted small">Aucune photo : ajoutez-en dans « Photos (galerie) ».</span><?php endif; ?>
    </div>
    <button class="btn btn-dark" id="btn-png"><i class="bi bi-download"></i> Télécharger (PNG)</button>
    <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer / PDF</button>
  </div>
  <div class="col-lg-8">
    <div id="affiche">
      <div class="photo" id="a-photo" style="background-image:url('<?= $photos ? '../uploads/' . e($photos[0]) : '' ?>')">
        <?php if ($h['logo']): ?><img class="logo" src="../uploads/<?= e($h['logo']) ?>" alt=""><?php endif; ?>
      </div>
      <div class="body">
        <h2><?= e($h['nom']) ?> <span style="color:#ffc107;font-size:20px"><?= str_repeat('★', (int) $h['etoiles']) ?></span></h2>
        <div><?= e($h['ville']) ?></div>
        <div class="slogan" id="a-slogan"></div>
        <?php if ($prix !== null && $prix !== false): ?><span class="prix">Dès <?= money($prix) ?> / nuit</span><?php endif; ?>
        <div style="margin-top:10px" id="a-contact"></div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
const $ = id => document.getElementById(id);
const sync = () => { $('a-slogan').textContent = '« ' + $('f-slogan').value + ' »'; $('a-contact').textContent = $('f-contact').value ? '📞 ' + $('f-contact').value : ''; };
$('f-slogan').oninput = $('f-contact').oninput = sync; sync();
document.querySelectorAll('.pick').forEach(img => img.onclick = () => {
  document.querySelectorAll('.pick').forEach(i => i.style.borderColor = 'transparent');
  img.style.borderColor = '#0d6efd';
  $('a-photo').style.backgroundImage = "url('" + img.dataset.src + "')";
});
$('btn-png').onclick = () => html2canvas($('affiche'), {scale: 2, useCORS: true}).then(c => {
  const a = document.createElement('a'); a.download = 'affiche-<?= (int) $hotelId ?>.png'; a.href = c.toDataURL('image/png'); a.click();
});
</script>
<?php footer_html();
