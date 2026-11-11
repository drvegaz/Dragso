<?php
// Sidhuvudet: banners, logga, meny, språkväljare och Boka. $current = sidans namn ('' = startsidan).
$current ??= cms_page();
$langs = cms_languages();
?>
<a class="skip" href="#innehall"><?= dr_t('ui.skip') ?></a>
<?php foreach (dr_notices() as $i => $n): ?>
<div class="notice" role="region" aria-label="Meddelande"><div class="wrap">
  <?= dr_text("notices.$i.text") ?>
  <?php if (($n['linkLabel'] ?? '') !== '' && ($n['linkHref'] ?? '') !== ''): ?><a href="<?= h($n['linkHref']) ?>"<?= dr_attr("notices.$i.linkLabel") ?>><?= dr_t("notices.$i.linkLabel") ?></a><?php endif; ?>
</div></div>
<?php endforeach; ?>
<header class="site-head">
  <div class="wrap">
    <a class="logo" href="<?= h(cms_url('')) ?>" aria-label="<?= dr_t('contact.name') ?>">
      <img class="l-light" src="/assets/img/logo.png" alt="<?= dr_t('contact.name') ?>" width="547" height="237">
      <img class="l-dark" src="/assets/img/logo-vit.png" alt="<?= dr_t('contact.name') ?>" width="412" height="113">
    </a>
    <button class="menu-btn" id="menuBtn" type="button" aria-expanded="false" aria-controls="mainnav"><?= dr_t('ui.menu') ?></button>
    <nav class="mainnav" id="mainnav" aria-label="<?= dr_t('ui.nav') ?>">
      <?php foreach ((array)dr_get('nav') as $i => $item): ?>
      <a href="<?= h(dr_url($item['page'])) ?>"<?= $item['page'] === $current || ($item['page'] === 'boende' && $current === 'orlogsbyn') ? ' aria-current="page"' : '' ?><?= dr_attr("nav.$i.label") ?>><?= dr_t("nav.$i.label") ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="head-tools">
      <?php if (count($langs) > 1): ?>
      <nav class="langs" aria-label="<?= dr_t('ui.language') ?>">
        <?php foreach ($langs as $l): ?>
        <a href="<?= h($l['url']) ?>" hreflang="<?= h($l['code']) ?>" lang="<?= h($l['code']) ?>" title="<?= h($l['name']) ?>"<?= $l['current'] ? ' aria-current="true"' : '' ?>><?= h(strtoupper($l['code'])) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
      <a class="btn btn-boka" href="<?= h(DR_BOOKING_URL) ?>" target="_blank" rel="noopener"<?= dr_attr('ui.book') ?>><?= dr_t('ui.book') ?></a>
    </div>
  </div>
</header>
