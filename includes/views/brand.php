<?php
/** @var array $brand */
$site = site_url();
$name = $brand['name'];
$ids = array_values(array_map(fn($p) => (int)$p['id'], array_filter(catalog(), fn($p) => $p['brand'] === $name)));
$count = count($ids);
$url = brand_url($name);
$min = min(array_map(fn($id) => product_min_price(catalog()[$id]) ?: PHP_INT_MAX, $ids) ?: [0]);
$segment = SEGMENTS[$brand['segment']] ?? '';
$description = $brand['description'] ?: "{$name} — " . ($segment ? mb_strtolower($segment) . ' парфюмерия' : 'парфюмерный дом') . ($brand['country'] ? ' (' . $brand['country'] . ')' : '') . ". В Perfume Space — {$count} " . plural_ru($count, 'аромат', 'аромата', 'ароматов') . " бренда на распив от 5 мл: попробуйте и носите оригинальные ароматы без покупки целого флакона.";
$seo = $brand['seo_text'] ?: "## Духи {$name} на распив\nВ каталоге Perfume Space — {$count} " . plural_ru($count, 'оригинальный аромат', 'оригинальных аромата', 'оригинальных ароматов') . " {$name}" . ($min && $min < PHP_INT_MAX ? ', цены от ' . format_price($min) . ' за 5 мл' : '') . ". Распив — это часть парфюма из оригинального флакона в атомайзере на 5, 10 или 15 мл. Так можно познакомиться с ароматом, взять его в поездку или собрать свой парфюмерный гардероб.\n\n## Почему у нас\n- оригинальная продукция: декларация соответствия и «Честный ЗНАК»;\n- бесплатная доставка по всей России;\n- подарочная упаковка и парфюм 2 мл в подарок от " . format_price((int)(get_site_config()['promos']['gift_threshold'] ?? 8000)) . ".";

layout_start([
    'page' => 'brand',
    'title' => $brand['meta_title'] ?: "Духи {$name} — купить на распив от 5 мл, {$count} " . plural_ru($count, 'аромат', 'аромата', 'ароматов') . ' | Perfume Space',
    'description' => $brand['meta_description'] ?: mb_substr(strip_tags($description), 0, 200),
    'canonical' => $url,
    'og_image' => $brand['logo'] ?: null,
]);
?>
<main class="listing">
  <div class="container">
    <?= breadcrumbs_html([['Бренды', '/brands/'], [$name, '']], $site) ?>
    <header class="listing__head listing__head--brand">
      <div class="brand-logo-box"><?php if ($brand['logo']): ?><img src="<?= h($brand['logo']) ?>" alt="<?= h($name) ?>" width="240" height="240"><?php else: ?><span><?= h($name) ?></span><?php endif; ?></div>
      <div>
        <div class="eyebrow">Бренд<?= $brand['country'] ? ' · ' . h($brand['country']) : '' ?><?= $segment ? ' · ' . h($segment) : '' ?></div>
        <h1 class="h-section"><?= h($name) ?></h1>
        <p class="lead"><?= h($description) ?></p>
      </div>
    </header>
  </div>
  <section class="section section--flush" id="catalog" aria-label="Ароматы <?= h($name) ?>">
    <div class="container"><?php catalog_block(['ids' => $ids, 'hide_brand' => true]); ?></div>
  </section>
  <section class="section section--panel">
    <div class="container prose seo-text"><?= render_text($seo) ?></div>
  </section>
</main>
<?php layout_end(['page' => 'brand', 'scope' => $ids]);
