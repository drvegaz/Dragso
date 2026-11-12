<?php
declare(strict_types=1);
require __DIR__ . '/includes/site.php';
$p = 'pages.aktiviteter';
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

  <div class="wrap split" style="padding-bottom:48px">
    <div class="prose">
      <?= dr_text("$p.summer.eyebrow", 'p', ' class="eyebrow"') ?>
      <?= dr_text("$p.summer.title", 'h2') ?>
      <?= dr_text("$p.summer.text", 'p') ?>
      <?= dr_text("$p.summer.note", 'p', ' class="aside-note"') ?>
    </div>
    <?= dr_img("$p.summer.image") ?>
  </div>

  <div class="band band-alt">
    <div class="wrap">
      <div class="section-head">
        <?= dr_text("$p.rent.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$p.rent.title", 'h2') ?>
        <?= dr_text("$p.rent.lead", 'p', ' class="lead"') ?>
      </div>
      <div class="rent">
        <?php foreach ((array)dr_get("$p.rent.list") as $i => $r): $r_ = "$p.rent.list.$i"; ?>
        <div class="rent-item">
          <?= dr_text("$r_.name", 'h3') ?>
          <?= dr_text("$r_.value", 'p', ' class="rent-price"') ?>
          <?php if (($r['note'] ?? '') !== ''): ?><?= dr_text("$r_.note", 'p', ' class="rent-note"') ?><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php foreach (['sauna', 'golf', 'terrace'] as $n => $s): $s_ = "$p.$s"; ?>
  <div class="band<?= $n % 2 ? ' band-alt' : '' ?>">
    <div class="wrap split<?= $n % 2 ? ' flip' : '' ?>">
      <?= dr_img("$s_.image") ?>
      <div class="prose">
        <?= dr_text("$s_.eyebrow", 'p', ' class="eyebrow"') ?>
        <?= dr_text("$s_.title", 'h2') ?>
        <?= dr_text("$s_.text", 'p') ?>
        <?= dr_rows("$s_.rows") ?>
        <?php if (dr_get("$s_.list")): ?><?= dr_text('ui.included', 'h3') ?><?= dr_list("$s_.list") ?><?php endif; ?>
        <?= dr_text("$s_.note", 'p', ' class="aside-note"') ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
</body>
</html>
