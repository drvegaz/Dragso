<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.integritet';
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
  <div class="wrap prose" style="padding-bottom:72px;gap:28px">
    <?php foreach ((array)dr_get("$p.sections") as $i => $s): ?>
    <section class="prose">
      <?= dr_text("$p.sections.$i.title", 'h2') ?>
      <?= dr_text("$p.sections.$i.text", 'p', '', true) ?>
    </section>
    <?php endforeach; ?>
    <section class="prose">
      <?= dr_text('contact.name', 'h2') ?>
      <p><?= dr_text('contact.address', 'span', '', true) ?><br>
        <a href="<?= h(dr_tel((string)dr_get('contact.phone'))) ?>"><?= dr_t('contact.phone') ?></a> · <a href="mailto:<?= dr_t('contact.email') ?>"><?= dr_t('contact.email') ?></a></p>
    </section>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
