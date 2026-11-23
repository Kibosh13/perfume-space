<?php
/** @var array $article */
$site = site_url();
$a = $article;
$url = article_url($a);
[$body, $toc] = render_article((string)($a['body'] ?? ''));
$tail = array_values(array_filter(shortcode_products(implode(',', (array)($a['products'] ?? []))), fn($p) => !empty($p)));
$mentioned = article_all_products($a);
// Читайте также: сначала статьи той же рубрики, затем свежие
$more = array_values(array_filter(articles(), fn($x) => $x['slug'] !== $a['slug']));
usort($more, fn($x, $y) => ((($y['category'] ?? '') === ($a['category'] ?? '')) <=> (($x['category'] ?? '') === ($a['category'] ?? ''))) ?: strcmp((string)($y['date'] ?? ''), (string)($x['date'] ?? '')));
$more = array_slice($more, 0, 3);
$desc = ($a['meta_description'] ?? '') ?: (($a['excerpt'] ?? '') ?: mb_substr(strip_tags($body), 0, 180));
$ld = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $a['title'],
    'description' => $desc,
    'datePublished' => $a['date'] ?? '',
    'dateModified' => ($a['updated'] ?? '') ?: ($a['date'] ?? ''),
    'mainEntityOfPage' => $site . $url,
    'image' => !empty($a['cover']) ? [$site . $a['cover']] : [],
    'publisher' => ['@type' => 'Organization', 'name' => 'Perfume Space', 'logo' => ['@type' => 'ImageObject', 'url' => $site . '/assets/img/logo-white.png']],
];
if ($mentioned) {
    $ld['mentions'] = array_map(fn($p) => ['@type' => 'Product', 'name' => $p['name'], 'url' => $site . product_url($p)], $mentioned);
}
layout_start([
    'page' => 'article',
    'title' => ($a['meta_title'] ?? '') ?: $a['title'] . ' — Библиотека парфюмера Perfume Space',
    'description' => $desc,
    'canonical' => $url,
    'og_type' => 'article',
    'og_title' => $a['title'],
    'og_image' => ($a['cover'] ?? '') ?: null,
    'jsonld' => [$ld],
]);
?>
<main class="article-page">
  <div class="container">
    <?= breadcrumbs_html([['Библиотека парфюмера', '/statiy/'], [$a['title'], '']], $site) ?>
    <div class="article-layout">
      <article class="article">
        <div class="post-card__meta"><time datetime="<?= h($a['date'] ?? '') ?>"><?= h(ru_date($a['date'] ?? '')) ?></time><?php if (!empty($a['category'])): ?><a href="/statiy/?cat=<?= urlencode($a['category']) ?>"><?= h($a['category']) ?></a><?php endif; ?><span><?= reading_minutes((string)($a['body'] ?? '')) ?> мин чтения</span></div>
        <h1 class="article__title"><?= h($a['title']) ?></h1>
        <?php if (!empty($a['excerpt'])): ?><p class="lead article__lead"><?= h($a['excerpt']) ?></p><?php endif; ?>
        <?php if (!empty($a['cover'])): ?><img class="article__cover" src="<?= h($a['cover']) ?>" alt="<?= h($a['title']) ?>" width="1200" height="700" fetchpriority="high"><?php endif; ?>
        <div class="prose article__body"><?= $body ?></div>
        <?php if ($tail): ?>
          <section class="article__tail">
            <h2 class="about__h">Ароматы из статьи</h2>
            <?= article_products_html($tail) ?>
          </section>
        <?php endif; ?>
      </article>
      <?php if (count($toc) > 1): ?>
        <aside class="article-toc">
          <div class="f-title">Содержание</div>
          <?php foreach ($toc as [$id, $t]): ?><a href="#<?= $id ?>"><?= h($t) ?></a><?php endforeach; ?>
        </aside>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($more): ?>
  <section class="section section--panel">
    <div class="container">
      <div class="section-head"><div><div class="eyebrow">Библиотека парфюмера</div><h2 class="h-section">Читайте <em>также</em></h2></div><a href="/statiy/" class="link-arrow">Все статьи <svg class="icon"><use href="#i-arrow"/></svg></a></div>
      <div class="posts posts--3">
        <?php foreach ($more as $x): $u = article_url($x); ?>
          <article class="post-card">
            <a href="<?= h($u) ?>" class="post-card__media"><?php if (!empty($x['cover'])): ?><img src="<?= h($x['cover']) ?>" alt="<?= h($x['title']) ?>" loading="lazy" width="800" height="500"><?php endif; ?></a>
            <div class="post-card__body">
              <div class="post-card__meta"><time datetime="<?= h($x['date'] ?? '') ?>"><?= h(ru_date($x['date'] ?? '')) ?></time></div>
              <h3 class="post-card__title"><a href="<?= h($u) ?>"><?= h($x['title']) ?></a></h3>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php layout_end(['page' => 'article']);
