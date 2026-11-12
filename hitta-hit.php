<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.hitta-hit';
$map = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Dragsö Camping, ' . str_replace("\n", ', ', (string)dr_get('contact.address')));
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/includes/partials/header.php'; ?>
<main id="innehall">
  <div class="wrap page-head">
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"><?= dr_t('ui.home') ?></a> / <?= dr_t("$p.title") ?></p>
    <?= dr_text("$p.title", 'h1') ?>
    <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
  </div>

  <div class="wrap find" style="padding-bottom:56px">
    <div class="prose">
      <?= dr_text("$p.ways.title", 'h2') ?>
      <dl class="ways">
        <div><dt><?= dr_t('ui.addressLabel') ?></dt><dd><?= dr_text('contact.address', 'span', '', true) ?><br><a href="<?= h($map) ?>" target="_blank" rel="noopener"><?= dr_t('ui.openMap') ?> ↗</a></dd></div>
        <?php foreach ((array)dr_get("$p.ways.rows") as $i => $r): ?>
        <div><?= dr_text("$p.ways.rows.$i.0", 'dt') ?><?= dr_text("$p.ways.rows.$i.1", 'dd') ?></div>
        <?php endforeach; ?>
      </dl>
    </div>
    <?php require __DIR__ . '/includes/partials/hours.php'; ?>
  </div>

  <div class="band band-alt">
    <div class="wrap split">
      <div class="prose">
        <?= dr_text("$p.arrival.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.arrival.title", 'h2') ?>
        <?= dr_rows("$p.arrival.rows") ?>
        <?= dr_list("$p.arrival.list") ?>
      </div>
      <?= dr_img("$p.arrival.image") ?>
    </div>
  </div>

  <div class="band">
    <div class="wrap">
      <div class="section-head">
        <?= dr_text("$p.around.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.around.title", 'h2') ?>
      </div>
      <div class="rent">
        <?php foreach ((array)dr_get("$p.around.list") as $i => $x): ?>
        <div class="rent-item"><?= dr_text("$p.around.list.$i.name", 'h3') ?><?= dr_text("$p.around.list.$i.text", 'p') ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
