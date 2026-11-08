<?php
$site = site_url();
$menu = collections_menu();
layout_start([
    'page' => 'collections',
    'title' => 'Категории и подборки парфюмерии на распив | Perfume Space',
    'description' => 'Женская, мужская, нишевая, шлейфовая парфюмерия, новинки и хиты продаж, ароматы по группам и концентрации — подборки Perfume Space.',
    'canonical' => '/category/',
]);
?>
<main class="listing">
  <div class="container">
    <?= breadcrumbs_html([['Подборки', '']], $site) ?>
    <header class="listing__head">
      <div class="eyebrow">Каталог</div>
      <h1 class="h-section">Категории и <em>подборки</em></h1>
    </header>
    <div class="col-groups">
      <?php foreach ($menu as $group => $items): ?>
        <div class="col-group">
          <h2 class="col-group__title"><?= h($group) ?></h2>
          <?php foreach ($items as $c): $n = count(collection_ids($c)); ?>
            <a class="brand-link" href="<?= h(collection_url($c)) ?>"><span><?= h($c['title']) ?></span><span><?= $n ?></span></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>
<?php layout_end(['page' => 'collections']);
