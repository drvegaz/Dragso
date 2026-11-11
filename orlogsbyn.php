<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.orlogsbyn';
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
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"><?= dr_t('ui.home') ?></a> / <a href="<?= h(cms_url('boende')) ?>"><?= dr_t('pages.boende.title') ?></a> / <?= dr_t("$p.title") ?></p>
    <?= dr_text("$p.title", 'h1') ?>
    <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
  </div>
  <div class="wrap cabin">
    <div class="cabin-main">
      <section>
        <?= dr_text("$p.themes.title", 'h2') ?>
        <?= dr_text("$p.themes.text", 'p', ' class="prose"') ?>
        <ul class="names">
          <?php foreach ((array)dr_get("$p.themes.cabins") as $i => $c): ?>
          <li><span class="num"><?= (int)$c['number'] ?></span><?= dr_text("$p.themes.cabins.$i.name", 'b') ?><?= $c['pets'] ? dr_ui('petsOk', 'span', ' class="pet"') : '' ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section>
        <?= dr_text("$p.living.title", 'h2') ?>
        <div class="prose"><?= dr_text("$p.living.body", 'p', '', true) ?></div>
      </section>
      <section>
        <?= dr_text("$p.equipment.title", 'h2') ?>
        <ul class="equip"><?php foreach ((array)dr_get("$p.equipment.list") as $i => $x): ?><li<?= dr_attr("$p.equipment.list.$i") ?>><?= dr_t("$p.equipment.list.$i") ?></li><?php endforeach; ?></ul>
      </section>
      <section>
        <?= dr_text("$p.terms.title", 'h2') ?>
        <?= dr_text("$p.terms.text", 'p', ' class="prose"') ?>
        <dl class="terms">
          <?php foreach ((array)dr_get("$p.terms.rows") as $i => $r): ?>
          <div><dt<?= dr_attr("$p.terms.rows.$i.0") ?>><?= dr_t("$p.terms.rows.$i.0") ?></dt><dd<?= dr_attr("$p.terms.rows.$i.1") ?>><?= dr_t("$p.terms.rows.$i.1") ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>
    </div>
    <aside class="aside" aria-label="<?= dr_t('ui.book') ?> <?= dr_t("$p.title") ?>">
      <p class="price"><?= dr_t('ui.perNight') ?><b><?= dr_price((int)dr_get("$p.price")) ?></b></p>
      <dl class="quick">
        <?php foreach ((array)dr_get("$p.facts.rows") as $i => $r): ?>
        <div><dt<?= dr_attr("$p.facts.rows.$i.0") ?>><?= dr_t("$p.facts.rows.$i.0") ?></dt><dd<?= dr_attr("$p.facts.rows.$i.1") ?>><?= dr_t("$p.facts.rows.$i.1") ?></dd></div>
        <?php endforeach; ?>
      </dl>
      <a class="btn btn-boka" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr('ui.seeDates') ?>><?= dr_t('ui.seeDates') ?></a>
      <small><?= dr_fill(dr_t("$p.facts.note")) ?></small>
    </aside>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
