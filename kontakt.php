<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.kontakt';
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
      <?= dr_text('ui.reception', 'h2') ?>
      <dl class="ways">
        <div><dt><?= dr_t('ui.call') ?></dt><dd class="num"><a href="<?= h(dr_tel((string)dr_get('contact.phone'))) ?>"<?= dr_attr('contact.phone') ?>><?= dr_t('contact.phone') ?></a></dd></div>
        <div><dt><?= dr_t('ui.emailLabel') ?></dt><dd><a href="mailto:<?= dr_t('contact.email') ?>"<?= dr_attr('contact.email') ?>><?= dr_t('contact.email') ?></a></dd></div>
        <div><dt><?= dr_t('ui.addressLabel') ?></dt><dd<?= dr_attr('contact.address', true) ?>><?= dr_t('contact.address', true) ?></dd></div>
      </dl>
      <?= dr_text("$p.note", 'p', ' class="aside-note"') ?>
    </div>
    <?php require __DIR__ . '/includes/partials/hours.php'; ?>
  </div>

  <div class="band band-alt">
    <div class="wrap two">
      <div class="prose">
        <?= dr_text("$p.restaurant.title", 'h2') ?>
        <?= dr_text("$p.restaurant.text", 'p') ?>
        <p class="num"><a href="<?= h(dr_tel((string)dr_get("$p.restaurant.phone"))) ?>"<?= dr_attr("$p.restaurant.phone") ?>><?= dr_t("$p.restaurant.phone") ?></a></p>
        <p><a href="https://havsviken.se" target="_blank" rel="noopener"<?= dr_attr("$p.restaurant.label") ?>><?= dr_t("$p.restaurant.label") ?></a> ↗</p>
      </div>
      <div class="prose">
        <?= dr_text("$p.shop.title", 'h2') ?>
        <?= dr_text("$p.shop.text", 'p') ?>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
