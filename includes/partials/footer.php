<?php
// Sidfoten: kontakt, öppettider (modulen oppettider), länkar och våra andra campingar.
?>
<footer class="site-foot">
  <div class="wrap">
    <div class="cols">
      <div>
        <?= dr_text('contact.name', 'h3') ?>
        <ul class="muted">
          <li<?= dr_attr('contact.address', true) ?>><?= dr_t('contact.address', true) ?></li>
          <li><a href="<?= h(dr_tel((string)dr_get('contact.phone'))) ?>"<?= dr_attr('contact.phone') ?>><?= dr_t('contact.phone') ?></a></li>
          <li><a href="mailto:<?= dr_t('contact.email') ?>"<?= dr_attr('contact.email') ?>><?= dr_t('contact.email') ?></a></li>
        </ul>
      </div>
      <div>
        <?= dr_text('ui.stayWithUs', 'h3') ?>
        <ul>
          <?php foreach ((array)dr_get('nav') as $i => $item): ?>
          <li><a href="<?= h(dr_url($item['page'])) ?>"><?= dr_t("nav.$i.label") ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <?php foreach ((array)dr_get('hours') as $g => $group): ?>
        <?= dr_text("hours.$g.title", 'h3') ?>
        <ul class="muted">
          <?php foreach ($group['rows'] as $r => $row): ?>
          <li><?= dr_text("hours.$g.rows.$r.0") ?> <span class="num"<?= dr_attr("hours.$g.rows.$r.1") ?>><?= dr_t("hours.$g.rows.$r.1") ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
      </div>
      <div>
        <?= dr_text('ui.otherCampings', 'h3') ?>
        <ul>
          <?php foreach ((array)dr_get('footer.links') as $i => $l): ?>
          <li><a href="<?= h($l['href']) ?>" target="_blank" rel="noopener"<?= dr_attr("footer.links.$i.label") ?>><?= dr_t("footer.links.$i.label") ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="bottom"><span>© <?= date('Y') ?> <?= dr_t('contact.name') ?></span><a href="<?= h(dr_url('integritet')) ?>"<?= dr_attr('ui.privacy') ?>><?= dr_t('ui.privacy') ?></a></div>
  </div>
</footer>
<script src="/assets/js/site.js" defer></script>
