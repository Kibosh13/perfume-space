<?php
$site = site_url();
$cfg = get_site_config();
$seo = $cfg['seo']['articles'] ?? [];
$all = articles();
$cats = article_categories();
$cat = trim((string)($_GET['cat'] ?? ''));
if ($cat !== '' && !isset($cats[$cat])) $cat = '';
$list = $cat === '' ? $all : array_values(array_filter($all, fn($a) => ($a['category'] ?? '') === $cat));
$per = 12;
$pages = max(1, (int)ceil(count($list) / $per));
$page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$items = array_slice($list, ($page - 1) * $per, $per);
$title = $seo['h1'] ?? '' ?: 'Библиотека парфюмера';
$qs = fn(array $p) => ($q = http_build_query(array_filter($p))) ? '?' . $q : '';
layout_start([
    'page' => 'articles',
    'title' => ($seo['title'] ?? '') ?: 'Библиотека парфюмера — статьи о парфюмерии и ароматах | Perfume Space' . ($page > 1 ? " — страница {$page}" : ''),
    'description' => ($seo['description'] ?? '') ?: 'Статьи Perfume Space: как выбрать аромат, ноты и аккорды, подборки по сезонам и поводам, советы по распиву и хранению парфюмерии.',
    'canonical' => '/statiy/' . $qs(['cat' => $cat, 'page' => $page > 1 ? $page : 0]),
]);
?>
<main class="listing">
  <div class="container">
    <?= breadcrumbs_html([[$title, '']], $site) ?>
    <header class="listing__head">
      <div class="eyebrow">Статьи</div>
      <h1 class="h-section"><?= h($title) ?></h1>
      <p class="lead"><?= h(($seo['intro'] ?? '') ?: 'Рассказываем об ароматах простым языком: как выбирать, носить и хранить парфюм, о нотах, брендах и сезонных подборках.') ?></p>
    </header>
    <?php if ($cats): ?>
      <div class="chips-nav">
        <a class="chip <?= $cat === '' ? 'is-active' : '' ?>" href="/statiy/">Все статьи<sup><?= count($all) ?></sup></a>
        <?php foreach ($cats as $c => $n): ?><a class="chip <?= $cat === $c ? 'is-active' : '' ?>" href="/statiy/<?= $qs(['cat' => $c]) ?>"><?= h($c) ?><sup><?= $n ?></sup></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($items): ?>
      <div class="posts">
        <?php foreach ($items as $i => $a): $url = article_url($a); ?>
          <article class="post-card<?= $page === 1 && $i === 0 && $cat === '' ? ' post-card--lead' : '' ?>">
            <a href="<?= h($url) ?>" class="post-card__media"><?php if (!empty($a['cover'])): ?><img src="<?= h($a['cover']) ?>" alt="<?= h($a['title']) ?>" loading="lazy" width="800" height="500"><?php endif; ?></a>
            <div class="post-card__body">
              <div class="post-card__meta"><time datetime="<?= h($a['date'] ?? '') ?>"><?= h(ru_date($a['date'] ?? '')) ?></time><?php if (!empty($a['category'])): ?><span><?= h($a['category']) ?></span><?php endif; ?><span><?= reading_minutes((string)($a['body'] ?? '')) ?> мин чтения</span></div>
              <h2 class="post-card__title"><a href="<?= h($url) ?>"><?= h($a['title']) ?></a></h2>
              <?php if (!empty($a['excerpt'])): ?><p class="post-card__text"><?= h($a['excerpt']) ?></p><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Страницы">
          <?php for ($n = 1; $n <= $pages; $n++): ?><a class="<?= $n === $page ? 'is-active' : '' ?>" href="/statiy/<?= $qs(['cat' => $cat, 'page' => $n > 1 ? $n : 0]) ?>"><?= $n ?></a><?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty"><h3>Статей пока нет</h3><p>Скоро здесь появятся материалы об ароматах.</p></div>
    <?php endif; ?>
  </div>
</main>
<?php layout_end(['page' => 'articles']);
