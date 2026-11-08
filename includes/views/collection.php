<?php
/** @var array $collection */
$site = site_url();
$col = $collection;
$ids = collection_ids($col);
$count = count($ids);
$url = collection_url($col);
$h1 = $col['h1'] ?: $col['title'];
$intro = $col['intro'] ?? '';
$seo = $col['seo_text'] ?? '';
if ($seo === '') {
    $seo = "## {$h1} на распив\n{$col['title']} в Perfume Space — оригинальные ароматы в атомайзерах 5, 10 и 15 мл. Распив позволяет познакомиться с ароматом, не покупая целый флакон, и собрать парфюмерный гардероб на каждый случай.\n\n- оригинальная продукция: декларация соответствия и «Честный ЗНАК»;\n- бесплатная доставка по всей России;\n- скидка от нескольких позиций и подарок 2 мл к крупному заказу.";
}
$related = array_values(array_filter(collections(), fn($c) => $c['slug'] !== $col['slug'] && array_intersect((array)($c['menu'] ?? []), (array)($col['menu'] ?? []))));

layout_start([
    'page' => 'collection',
    'title' => $col['meta_title'] ?: "{$h1} — купить на распив от 5 мл | Perfume Space",
    'description' => $col['meta_description'] ?: ($intro ?: "{$col['title']}: {$count} оригинальных ароматов на распив 5, 10 и 15 мл. Бесплатная доставка по России, подарочная упаковка."),
    'canonical' => $url,
    'og_image' => $col['image'] ?: null,
]);
?>
<main class="listing">
  <div class="container">
    <?= breadcrumbs_html([['Подборки', '/category/'], [$col['title'], '']], $site) ?>
    <header class="listing__head<?= $col['image'] ? ' listing__head--image' : '' ?>">
      <?php if ($col['image']): ?><img class="listing__img" src="<?= h($col['image']) ?>" alt="" width="1200" height="500"><?php endif; ?>
      <div>
        <div class="eyebrow"><?= h(implode(' · ', (array)($col['menu'] ?? [])) ?: 'Подборка') ?></div>
        <h1 class="h-section"><?= h($h1) ?></h1>
        <?php if ($intro): ?><p class="lead"><?= h($intro) ?></p><?php endif; ?>
      </div>
    </header>
    <?php if ($related): ?>
      <div class="chips-nav"><?php foreach (array_slice($related, 0, 16) as $r): ?><a class="chip" href="<?= h(collection_url($r)) ?>"><?= h($r['title']) ?></a><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
  <section class="section section--flush" id="catalog" aria-label="<?= h($col['title']) ?>">
    <div class="container"><?php catalog_block(['ids' => $ids]); ?></div>
  </section>
  <section class="section section--panel">
    <div class="container prose seo-text"><?= render_text($seo) ?></div>
  </section>
</main>
<?php layout_end(['page' => 'collection', 'scope' => $ids, 'defaultSort' => $col['default_sort'] ?? '']);
