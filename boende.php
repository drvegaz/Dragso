<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.boende';
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/includes/partials/header.php'; ?>
<main id="innehall">
  <div class="wrap page-head">
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"<?= dr_attr('ui.home') ?>><?= dr_t('ui.home') ?></a> / <?= dr_t("$p.title") ?></p>
    <?= dr_text("$p.title", 'h1') ?>
    <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
    <div class="chips" role="group" aria-label="<?= dr_t("$p.title") ?>" data-filter-chips hidden>
      <button class="chip" type="button" data-filter="all" aria-pressed="true"><?= dr_t('ui.all') ?></button>
      <?php foreach ((array)dr_get("$p.groups") as $g => $group): ?>
      <button class="chip" type="button" data-filter="g<?= $g ?>" aria-pressed="false"<?= dr_attr("$p.groups.$g.label") ?>><?= dr_t("$p.groups.$g.label") ?></button>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="wrap" style="padding-bottom:72px">
    <?php foreach ((array)dr_get("$p.groups") as $g => $group): $g_ = "$p.groups.$g"; ?>
    <div class="group" data-group="g<?= $g ?>">
      <div class="group-head"><?= dr_text("$g_.title", 'h2') ?><?= dr_text("$g_.sub", 'p') ?></div>
      <div class="stays">
        <?php foreach ($group['items'] as $i => $s): $s_ = "$g_.items.$i"; ?>
        <article class="stay">
          <?php if ($s['image'] !== ''): ?>
          <div class="pic"><?= dr_img("$s_.image") ?></div>
          <?php else: ?>
          <div class="pic none"><?= dr_ui('noImage') ?></div>
          <?php endif; ?>
          <div class="body">
            <?= dr_text("$s_.name", 'h3') ?>
            <ul class="specs"><?php foreach ($s['list'] as $n => $x): ?><li<?= dr_attr("$s_.list.$n") ?>><?= dr_t("$s_.list.$n") ?></li><?php endforeach; ?></ul>
            <?= dr_text("$s_.text", 'p') ?>
            <div class="foot">
              <span class="from"><?= $s['price'] ? '<b>' . dr_price((int)$s['price'], "$s_.price") . '</b>' : dr_t('ui.priceInBooking') ?></span>
              <?php if ($s['page'] !== ''): ?><a href="<?= h(dr_url($s['page'])) ?>"<?= dr_attr('ui.readMore') ?>><?= dr_t('ui.readMore') ?></a>
              <?php else: ?><a href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr('ui.book') ?>><?= dr_t('ui.book') ?></a><?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
