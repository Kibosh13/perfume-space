<?php
$site = site_url();
$brands = brand_list();
$groups = [];
foreach ($brands as $name => $count) {
    $l = strtoupper(substr($name, 0, 1));
    $groups[ctype_alpha($l) ? $l : '#'][$name] = $count;
}
layout_start([
    'page' => 'brands',
    'title' => 'Все бренды парфюмерии на распив — ' . count($brands) . ' парфюмерных домов | Perfume Space',
    'description' => 'Бренды нишевой и люксовой парфюмерии в Perfume Space: Tom Ford, Kilian, Amouage, Creed, Xerjoff и ещё ' . (count($brands) - 5) . '. Оригинальные ароматы на распив от 5 мл.',
    'canonical' => '/brands/',
]);
?>
<main class="listing">
  <div class="container">
    <?= breadcrumbs_html([['Бренды', '']], $site) ?>
    <header class="listing__head">
      <div class="eyebrow">Бренды</div>
      <h1 class="h-section"><?= count($brands) ?> парфюмерных домов</h1>
    </header>
    <div class="abc" id="abc">
      <button type="button" class="is-active" data-letter="ALL">Все</button>
      <?php foreach (array_keys($groups) as $l): ?><button type="button" data-letter="<?= h($l) ?>"><?= h($l) ?></button><?php endforeach; ?>
    </div>
    <div class="brand-index">
      <?php foreach ($groups as $l => $items): ?>
        <div class="brand-group" data-group="<?= h($l) ?>">
          <div class="brand-group__letter"><?= h($l) ?></div>
          <?php foreach ($items as $name => $count): ?><a class="brand-link" href="<?= h(brand_url($name)) ?>"><span><?= h($name) ?></span><span><?= $count ?></span></a><?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>
<?php layout_end(['page' => 'brands']);
