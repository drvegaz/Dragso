<?php
// Mall för en stugsida (Örlogsbyn, Stugsviter, Fiskebyn …). Sidfilen sätter $cabin till nyckeln i pages:
//   <?php $cabin = 'fiskebyn'; require __DIR__ . '/includes/cabin.php';
// Innehållet: title, summary, lead, image/imageAlt (valfri), price (0 = se bokningen), bookByPhone (valfri),
// themes { title, text, cabins: [{ number, name, text?, pets }] } (valfri), living, equipment, terms, facts.
declare(strict_types=1);
require __DIR__ . '/site.php';
$p = "pages.$cabin";
$c = (array)dr_get($p);
?><!doctype html>
<html lang="<?= h(cms_lang()) ?>">
<head>
<?php dr_head("$p.title", "$p.summary"); ?>
</head>
<body>
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="innehall">
  <?php if (($c['image'] ?? '') !== ''): ?>
  <div class="cabin-hero"><?= dr_img("$p.image", '', false) ?></div>
  <?php endif; ?>
  <div class="wrap page-head" style="padding-bottom:0">
    <p class="crumbs"><a href="<?= h(cms_url('')) ?>"><?= dr_t('ui.home') ?></a> / <a href="<?= h(cms_url('boende')) ?>"><?= dr_t('pages.boende.title') ?></a> / <?= dr_t("$p.title") ?></p>
    <?= dr_text("$p.title", 'h1') ?>
    <?= dr_text("$p.lead", 'p', ' class="lead"') ?>
  </div>
  <div class="wrap cabin">
    <div class="cabin-main">
      <?php if (!empty($c['themes'])): ?>
      <section>
        <?= dr_text("$p.themes.title", 'h2') ?>
        <?= dr_text("$p.themes.text", 'p', ' class="prose"') ?>
        <ul class="names<?= !empty($c['themes']['cabins'][0]['text']) ? ' wide' : '' ?>">
          <?php foreach ($c['themes']['cabins'] as $i => $t): $t_ = "$p.themes.cabins.$i"; ?>
          <li><span class="num"><?= (int)$t['number'] ?></span><?= dr_text("$t_.name", 'b') ?><?php if (!empty($t['text'])): ?><?= dr_text("$t_.text", 'span', ' class="desc"') ?><?php endif; ?><?= $t['pets'] ? dr_ui('petsOk', 'span', ' class="pet"') : '' ?></li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
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
        <?= dr_rows("$p.terms.rows") ?>
      </section>
    </div>
    <aside class="aside" aria-label="<?= dr_t('ui.book') ?> – <?= dr_t("$p.title") ?>">
      <p class="price"><?= dr_t('ui.perNight') ?><b><?= ($c['price'] ?? 0) ? dr_price((int)$c['price']) : dr_t('ui.priceInBooking') ?></b></p>
      <?= dr_rows("$p.facts.rows", 'quick') ?>
      <?php if (!empty($c['bookByPhone'])): ?>
      <a class="btn btn-boka" href="<?= h(dr_tel((string)dr_get('contact.phone'))) ?>"<?= dr_attr('ui.bookByPhone') ?>><?= dr_t('ui.bookByPhone') ?></a>
      <?= dr_text('ui.bookByPhoneNote', 'small') ?>
      <?php else: ?>
      <a class="btn btn-boka" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr('ui.seeDates') ?>><?= dr_t('ui.seeDates') ?></a>
      <?php endif; ?>
      <small><?= dr_fill(dr_t("$p.facts.note")) ?></small>
    </aside>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
