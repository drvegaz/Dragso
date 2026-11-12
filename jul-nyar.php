<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.jul-nyar';
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/includes/partials/header.php'; ?>
<main id="innehall">
  <div class="cabin-hero"><?= dr_img("$p.image", '', false) ?></div>
  <div class="wrap page-head">
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"><?= dr_t('ui.home') ?></a> / <?= dr_t("$p.title") ?></p>
    <?= dr_text("$p.title", 'h1') ?>
    <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
  </div>
  <div class="band band-alt">
    <div class="wrap">
      <div class="section-head">
        <?= dr_text("$p.activities.title", 'h2') ?>
        <?= dr_text("$p.activities.text", 'p', ' class="lead"') ?>
      </div>
      <div class="two">
        <?php foreach (['kids' => 'forKids', 'adults' => 'forAdults'] as $k => $label): ?>
        <div class="prose">
          <?= dr_text("ui.$label", 'h3') ?>
          <div class="rent">
            <?php foreach ((array)dr_get("$p.activities.$k") as $i => $a): ?>
            <div class="rent-item"><?= dr_text("$p.activities.$k.$i.name", 'h3') ?><?= dr_text("$p.activities.$k.$i.text", 'p') ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="band">
    <div class="wrap two">
      <div class="prose">
        <?= dr_text("$p.stay.title", 'h2') ?>
        <?= dr_text("$p.stay.text", 'p') ?>
        <?= dr_text("$p.stay.note", 'p', ' class="aside-note"') ?>
        <p><a class="btn btn-boka" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr('ui.bookCabin') ?>><?= dr_t('ui.bookCabin') ?></a>
          <a class="btn btn-ghost" href="<?= h(cms_url('boende')) ?>"><?= dr_t('pages.boende.title') ?></a></p>
      </div>
      <div class="prose">
        <?= dr_img("$p.sauna.image") ?>
        <?= dr_text("$p.sauna.title", 'h2') ?>
        <?= dr_text("$p.sauna.text", 'p') ?>
        <p><a href="<?= h(cms_url('aktiviteter')) ?>"<?= dr_attr('ui.readMore') ?>><?= dr_t('ui.readMore') ?></a></p>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
