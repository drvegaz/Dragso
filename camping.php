<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.camping';
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/includes/partials/header.php'; ?>
<main id="innehall">
  <div class="wrap page-head">
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"><?= dr_t('ui.home') ?></a> / <?= dr_t('nav.1.label') ?></p>
    <?= dr_text("$p.title", 'h1') ?>
  </div>
  <div class="wrap">
    <div class="split">
      <div class="prose">
        <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
        <?= dr_text("$p.text", 'p') ?>
      </div>
      <?= dr_img("$p.image") ?>
    </div>
    <div class="stat-row">
      <?php foreach ((array)dr_get("$p.facts") as $i => $f): ?>
      <div><?= dr_text("$p.facts.$i.value", 'b') ?><?= dr_text("$p.facts.$i.label") ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="band">
    <div class="wrap">
      <div class="section-head">
        <?= dr_text("$p.zones.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.zones.title", 'h2') ?>
        <?= dr_text("$p.zones.lead", 'p', ' class="lead"') ?>
      </div>
      <div class="zones">
        <?php foreach ((array)dr_get("$p.zones.list") as $i => $z): $z_ = "$p.zones.list.$i";
          $color = in_array($z['color'], ['lila', 'bla', 'gron', 'orange', 'rod'], true) ? $z['color'] : 'bla'; ?>
        <div class="zone<?= $z['bookable'] ? '' : ' nobook' ?>" style="--c:var(--z-<?= $color ?>)">
          <span class="sw"></span>
          <div><h3><?= dr_text("$z_.name") ?> <small><?= $z['bookable'] ? dr_t('ui.bookable') : dr_t('ui.notBookable') ?></small></h3><?= dr_text("$z_.text", 'p') ?></div>
          <p class="zp"><?php if (!$z['bookable']): ?><b><?= dr_t('ui.askReception') ?></b><?php elseif ($z['price']): ?><b><?= dr_price((int)$z['price']) ?></b><?php else: ?><b><?= dr_t('ui.priceInBooking') ?></b><?php endif; ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="help">
        <p><?= dr_text("$p.help.text") ?> <a class="num" href="<?= h(dr_tel((string)dr_get('contact.phone'))) ?>"><?= dr_t('contact.phone') ?></a> · <a href="mailto:<?= dr_t('contact.email') ?>"><?= dr_t('contact.email') ?></a></p>
        <a class="btn btn-boka" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr("$p.help.label") ?>><?= dr_t("$p.help.label") ?></a>
      </div>
    </div>
  </div>
  <div class="band band-alt">
    <div class="wrap split">
      <?= dr_img("$p.tent.image") ?>
      <div class="prose">
        <?= dr_text("$p.tent.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.tent.title", 'h2') ?>
        <?= dr_text("$p.tent.text", 'p') ?>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
