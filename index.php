<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.start';
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/includes/partials/header.php'; ?>
<main id="innehall">
  <div class="hero">
    <?= dr_img("$p.hero.image", ' class="hero-img" fetchpriority="high"', false) ?>
    <div class="wrap">
      <div class="hero-text">
        <p class="eyebrow"><span class="stars" aria-hidden="true">★★★★</span> <?= dr_text("$p.hero.eyebrow") ?></p>
        <?= dr_text("$p.hero.title", 'h1') ?>
        <?= dr_text("$p.hero.text", 'p') ?>
      </div>
      <div class="book-card">
        <?= dr_text("$p.book.title", 'h2') ?>
        <?php foreach ((array)dr_get("$p.book.rows") as $i => $row): ?>
        <a class="book-row" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener">
          <span><?= dr_text("$p.book.rows.$i.name", 'b') ?><?= dr_text("$p.book.rows.$i.sub", 'small') ?></span>
          <span class="price"><span><?= dr_price((int)$row['price'], "$p.book.rows.$i.price") ?></span></span>
        </a>
        <?php endforeach; ?>
        <?= dr_text("$p.book.note", 'p', ' class="fine"') ?>
      </div>
    </div>
  </div>
  <div class="facts">
    <div class="wrap">
      <?php foreach ((array)dr_get("$p.facts") as $i => $f): ?>
      <div><?= dr_text("$p.facts.$i.value", 'b') ?><?= dr_text("$p.facts.$i.label") ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="band">
    <div class="wrap">
      <div class="section-head">
        <?= dr_text("$p.stay.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.stay.title", 'h2') ?>
      </div>
      <div class="paths">
        <?php foreach ((array)dr_get("$p.stay.cards") as $i => $c): $c_ = "$p.stay.cards.$i"; ?>
        <a class="path" href="<?= h(dr_url($c['page'])) ?>">
          <div class="ph"><?= dr_img("$c_.image") ?></div>
          <div class="body"><?= dr_text("$c_.tag", 'p', ' class="from"') ?><?= dr_text("$c_.name", 'h3') ?><?= dr_text("$c_.text", 'p') ?><?= dr_text("$c_.label", 'span', ' class="go"') ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="band band-alt">
    <div class="wrap">
      <div class="section-head">
        <?= dr_text("$p.life.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.life.title", 'h2') ?>
        <?= dr_text("$p.life.lead", 'p', ' class="lead"') ?>
      </div>
      <div class="mosaic">
        <?php foreach ((array)dr_get("$p.life.tiles") as $i => $t): $t_ = "$p.life.tiles.$i"; ?>
        <figure class="tile<?= ['big', '', '', 'wide'][$i % 4] ? ' ' . ['big', '', '', 'wide'][$i % 4] : '' ?>"><?= dr_img("$t_.image") ?><figcaption><?= dr_text("$t_.name", 'b') ?><?= dr_text("$t_.text") ?></figcaption></figure>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="band">
    <div class="wrap">
      <div class="season">
        <?= dr_img("$p.season.image") ?>
        <div class="txt">
          <?= dr_text("$p.season.eyebrow", 'p', ' class="eyebrow"') ?>
          <?= dr_text("$p.season.title", 'h2') ?>
          <?= dr_text("$p.season.text", 'p') ?>
          <a class="btn btn-ghost" href="<?= h(dr_url((string)dr_get("$p.season.page"))) ?>"<?= dr_attr("$p.season.label") ?>><?= dr_t("$p.season.label") ?></a>
        </div>
      </div>
    </div>
  </div>

  <div class="band band-alt">
    <div class="wrap find">
      <div class="prose">
        <?= dr_text("$p.find.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.find.title", 'h2') ?>
        <dl class="ways">
          <div><dt><?= dr_t('ui.reception') ?></dt><dd<?= dr_attr('contact.address', true) ?>><?= dr_t('contact.address', true) ?></dd></div>
          <?php foreach ((array)dr_get("$p.find.rows") as $i => $r): ?>
          <div><dt<?= dr_attr("$p.find.rows.$i.0") ?>><?= dr_t("$p.find.rows.$i.0") ?></dt><dd<?= dr_attr("$p.find.rows.$i.1") ?>><?= dr_t("$p.find.rows.$i.1") ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </div>
      <?php require __DIR__ . '/includes/partials/hours.php'; ?>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
