<?php
// Öppettider (modulen oppettider), telefon och e-post i en ruta. Används på startsidan, Hitta hit och Kontakt.
?>
<div class="hours">
  <?php foreach ((array)dr_get('hours') as $g => $group): ?>
  <?= dr_text("hours.$g.title", 'h3') ?>
  <table>
    <?php foreach ($group['rows'] as $r => $row): ?>
    <tr><td<?= dr_attr("hours.$g.rows.$r.0") ?>><?= dr_t("hours.$g.rows.$r.0") ?></td><td<?= dr_attr("hours.$g.rows.$r.1") ?>><?= dr_t("hours.$g.rows.$r.1") ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endforeach; ?>
  <p class="num"><a href="<?= h(dr_tel((string)dr_get('contact.phone'))) ?>"<?= dr_attr('contact.phone') ?>><?= dr_t('contact.phone') ?></a></p>
  <p><a href="mailto:<?= dr_t('contact.email') ?>"<?= dr_attr('contact.email') ?>><?= dr_t('contact.email') ?></a></p>
</div>
