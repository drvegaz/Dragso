<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.stallplatser';
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/includes/partials/header.php'; ?>
<main id="innehall">
  <div class="cabin-hero"><?= dr_img("$p.image", '', false) ?></div>
  <div class="wrap page-head" style="padding-bottom:0">
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"><?= dr_t('ui.home') ?></a> / <a href="<?= h(cms_url('camping')) ?>"><?= dr_t('nav.1.label') ?></a> / <?= dr_t("$p.title") ?></p>
    <?= dr_text("$p.title", 'h1') ?>
    <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
  </div>
  <div class="wrap two" style="padding-block:40px 72px">
    <div class="prose">
      <?= dr_text("$p.place.title", 'h2') ?>
      <?= dr_text("$p.place.text", 'p') ?>
      <?= dr_text("$p.place.note", 'p', ' class="aside-note"') ?>
      <p><a class="btn btn-boka" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr('ui.bookPitch') ?>><?= dr_t('ui.bookPitch') ?></a></p>
    </div>
    <div class="prose">
      <?= dr_text("$p.service.title", 'h2') ?>
      <?= dr_list("$p.service.list") ?>
      <?= dr_text("$p.service.note", 'p', ' class="aside-note"') ?>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
