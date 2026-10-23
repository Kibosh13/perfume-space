<?php
require_once __DIR__ . '/includes/functions.php';
handle_legacy_redirects();

$config = get_site_config();
$products = get_products();

$brands = [];
$genders = ['Женские' => 0, 'Мужские' => 0, 'Унисекс' => 0];
foreach ($products as $p) {
    $brands[$p['brand']] = ($brands[$p['brand']] ?? 0) + 1;
    if (isset($genders[$p['gender']])) {
        $genders[$p['gender']]++;
    }
}
ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);

$brand_groups = [];
foreach ($brands as $name => $count) {
    $letter = strtoupper(substr($name, 0, 1));
    $brand_groups[ctype_alpha($letter) ? $letter : '#'][$name] = $count;
}

$catalog = array_map(fn($p) => [
    (int)$p['id'],
    $p['brand'],
    $p['short_name'] ?: $p['name'],
    $p['name'],
    $p['gender'],
    (int)($p['volumes']['5'] ?? 0),
    (int)($p['volumes']['10'] ?? 0),
    (int)($p['volumes']['15'] ?? 0),
    !empty($p['in_stock']) ? 1 : 0,
    !empty($p['is_hit']) ? 1 : 0,
    $p['image'] ?? '',
    $p['family'] ?? '',
    (int)($p['discount'] ?? 0),
], $products);

$c = $config['contacts'] ?? [];
$legal = $config['legal'] ?? [];
$promos = $config['promos'] ?? [];
$gift_from = (int)($promos['gift_threshold'] ?? 8000);
$bundle_min = (int)($promos['bundle_min_items'] ?? 5);
$bundle_pct = (int)($promos['bundle_discount_percent'] ?? 10);
$metrika_id = $config['integrations']['yandex_metrika_id'] ?? '';
$webmaster = $config['integrations']['yandex_webmaster_code'] ?? '';
$site = rtrim($config['domain'] ?? 'https://perfume-space.ru', '/');
$avito = $c['avito_url'] ?? '';
$email = $c['email'] ?? '';

function contact_value(?string $value, bool $link = false): string {
    if (!is_filled($value)) {
        return '<span class="tbd">' . h($value ?: '[заполнить]') . '</span>';
    }
    return $link ? '<a href="' . h($value) . '" target="_blank" rel="noopener">' . h($value) . '</a>' : h($value);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Perfume Space — оригинальная парфюмерия на распив от 5 мл</title>
  <meta name="description" content="Оригинальная нишевая и люксовая парфюмерия на распив 5, 10 и 15 мл: более 1 000 ароматов, 90 брендов. Декларация соответствия, «Честный ЗНАК», доставка по России.">
  <link rel="canonical" href="<?= h($site) ?>/">
  <?php if (is_filled($webmaster)): ?><meta name="yandex-verification" content="<?= h($webmaster) ?>"><?php endif; ?>
  <meta name="theme-color" content="#05070c">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">

  <meta property="og:type" content="website">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:site_name" content="Perfume Space">
  <meta property="og:title" content="Perfume Space — оригинальная парфюмерия на распив">
  <meta property="og:description" content="Более 1 000 оригинальных ароматов 90 брендов на распив от 5 мл. Подарочная упаковка и доставка по всей России.">
  <meta property="og:url" content="<?= h($site) ?>/">
  <meta property="og:image" content="<?= h($site) ?>/assets/img/og-cover.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="preload" as="image" href="/assets/img/hero.webp" fetchpriority="high">
  <link rel="stylesheet" href="/assets/css/style.css?v=4">

  <script type="application/ld+json">
  <?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'Organization',
      'name' => 'Perfume Space',
      'legalName' => $legal['entity'] ?? '',
      'taxID' => $legal['inn'] ?? '',
      'foundingDate' => '2018',
      'url' => $site . '/',
      'logo' => $site . '/assets/img/logo-white.png',
      'areaServed' => 'RU',
      'sameAs' => array_values(array_filter([
          is_filled($c['telegram'] ?? '') ? $c['telegram'] : null,
          is_filled($c['vk'] ?? '') ? $c['vk'] : null,
      ])),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
  </script>

  <?php if (is_filled($metrika_id)): ?>
  <script>
    window.PS_METRIKA_ID = <?= (int)$metrika_id ?>;
    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();
    k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
    ym(<?= (int)$metrika_id ?>, "init", { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true });
  </script>
  <?php endif; ?>
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1.2 12H6.2z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/></symbol>
  <symbol id="i-box" viewBox="0 0 24 24"><path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 8h16M4 16h16"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16M14 6l6 6-6 6"/></symbol>
  <symbol id="i-left" viewBox="0 0 24 24"><path d="M20 12H4M10 6l-6 6 6 6"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></symbol>
  <symbol id="i-fast" viewBox="0 0 24 24"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></symbol>
  <symbol id="i-tag" viewBox="0 0 24 24"><path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/></symbol>
  <symbol id="i-gift" viewBox="0 0 24 24"><path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13"/><path d="M12 7c-1.5-3-5-3-5-1s3 1 5 1zm0 0c1.5-3 5-3 5-1s-3 1-5 1z"/></symbol>
  <symbol id="i-doc" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/></symbol>
  <symbol id="i-code" viewBox="0 0 24 24"><path d="M4 4h4v4H4zM16 4h4v4h-4zM4 16h4v4H4z"/><path d="M12 4v4M12 12h8M16 16h4v4h-4zM12 16v4M4 12h4"/></symbol>
  <symbol id="i-star" viewBox="0 0 24 24"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></symbol>
  <symbol id="i-filter" viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></symbol>
</svg>

<div class="topbar">
  <div class="container topbar__inner">
    <div class="topbar__track">
      <?php for ($i = 0; $i < 2; $i++): ?>
        <span>Парфюм 2 мл в подарок при заказе от <?= format_price($gift_from) ?></span>
        <span>Скидка <?= $bundle_pct ?>% от <?= $bundle_min ?> позиций в заказе</span>
        <span>Доставка по всей России</span>
        <span>Ежедневно <?= h($config['work_hours'] ?? '') ?></span>
      <?php endfor; ?>
    </div>
  </div>
</div>

<header class="header">
  <div class="container header__inner">
    <a href="/" class="logo" aria-label="Perfume Space — на главную">
      <img src="/assets/img/logo-white.png" alt="Perfume Space" width="56" height="44">
      <span class="logo__word">
        <span class="logo__name">Perfume<br>Space</span>
      </span>
    </a>

    <nav class="nav" id="nav" aria-label="Основное меню">
      <a href="#catalog">Каталог</a>
      <a href="#brands">Бренды</a>
      <a href="#promo">Акции</a>
      <a href="#original">Оригинальность</a>
      <a href="#delivery">Доставка</a>
      <a href="#contacts">Контакты</a>
    </nav>

    <div class="tools">
      <button class="tool" type="button" data-open="search" aria-label="Поиск"><svg class="icon"><use href="#i-search"/></svg></button>
      <button class="tool" type="button" data-open="notes" aria-label="Уведомления" data-hide-mobile><svg class="icon"><use href="#i-bell"/></svg><span class="tool__count" data-count="notes"></span></button>
      <button class="tool" type="button" data-open="orders" aria-label="Мои заказы"><svg class="icon"><use href="#i-box"/></svg><span class="tool__count" data-count="orders"></span></button>
      <button class="tool" type="button" data-open="favs" aria-label="Избранное"><svg class="icon"><use href="#i-heart"/></svg><span class="tool__count" data-count="favs"></span></button>
      <button class="tool" type="button" data-open="cart" aria-label="Корзина"><svg class="icon"><use href="#i-bag"/></svg><span class="tool__count" data-count="cart"></span></button>
      <button class="tool burger" type="button" id="burger" aria-label="Меню"><svg class="icon"><use href="#i-menu"/></svg></button>
    </div>
  </div>

  <div class="search-panel" id="search">
    <div class="container">
      <div class="search-panel__field">
        <svg class="icon"><use href="#i-search"/></svg>
        <input type="search" id="searchInput" placeholder="Бренд или название аромата" autocomplete="off" aria-label="Поиск по каталогу">
        <button class="close" type="button" data-close="search" aria-label="Закрыть поиск"><svg class="icon"><use href="#i-close"/></svg></button>
      </div>
      <div class="search-results" id="searchResults"></div>
    </div>
  </div>
</header>

<main>
  <section class="hero">
    <div class="hero__bg"><img src="/assets/img/hero.webp" alt="" width="1920" height="1080" fetchpriority="high"></div>
    <div class="container hero__content">
      <div class="hero__text">
        <div class="eyebrow"><?= h($config['hero_badge'] ?? '') ?></div>
        <h1 class="h-display"><?= h($config['hero_title'] ?? '') ?></h1>
        <p class="lead"><?= h($config['hero_subtitle'] ?? '') ?></p>
        <div class="hero__ctas">
          <a href="#catalog" class="btn btn--primary" data-goal="hero_catalog">Выбрать аромат <svg class="icon icon--arrow"><use href="#i-arrow"/></svg></a>
          <a href="#request" class="btn btn--line">Помочь с выбором</a>
        </div>
      </div>
      <div class="hero__facts">
        <div class="fact"><div class="fact__val">2018</div><div class="fact__lbl">работаем с этого года</div></div>
        <div class="fact"><div class="fact__val">10 000<small>+</small></div><div class="fact__lbl">покупателей</div></div>
        <div class="fact"><div class="fact__val">5,0</div><div class="fact__lbl">более 3 500 отзывов</div></div>
        <div class="fact"><div class="fact__val">1 000<small>+</small></div><div class="fact__lbl">ароматов · <?= count($brands) ?> брендов</div></div>
      </div>
    </div>
  </section>

  <section class="section section--tight" aria-label="Почему выбирают нас">
    <div class="container benefits">
      <div class="benefit reveal">
        <svg class="icon"><use href="#i-shield"/></svg>
        <h3>Оригинал с документами</h3>
        <p>Декларация соответствия и маркировка «Честный ЗНАК» на продукцию.</p>
      </div>
      <div class="benefit reveal">
        <svg class="icon"><use href="#i-fast"/></svg>
        <h3>Быстрая отправка</h3>
        <p>Обычно отправляем заказ в течение суток после оформления.</p>
      </div>
      <div class="benefit reveal">
        <svg class="icon"><use href="#i-tag"/></svg>
        <h3>Цены распива</h3>
        <p>Нишевые и люксовые ароматы от 5 мл — без покупки целого флакона.</p>
      </div>
      <div class="benefit reveal">
        <svg class="icon"><use href="#i-gift"/></svg>
        <h3>Подарочная упаковка</h3>
        <p>Каждый заказ упакован так, что его можно сразу дарить.</p>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="cats-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Категории</div>
          <h2 class="h-section" id="cats-title">Для неё, для него <em>и для всех</em></h2>
        </div>
        <a href="#catalog" class="link-arrow">Весь каталог <svg class="icon"><use href="#i-arrow"/></svg></a>
      </div>
      <div class="cats">
        <?php foreach ([['Женские', 'Для неё', 'cat-women'], ['Мужские', 'Для него', 'cat-men'], ['Унисекс', 'Унисекс', 'cat-unisex']] as [$g, $title, $img]): ?>
          <a href="#catalog" class="cat reveal" data-gender="<?= h($g) ?>">
            <img src="/assets/img/<?= $img ?>.webp" alt="<?= h($title) ?> — парфюмерия на распив" loading="lazy" width="720" height="960">
            <div class="cat__body">
              <div>
                <div class="cat__count"><?= $genders[$g] ?> <?= plural_ru($genders[$g], 'аромат', 'аромата', 'ароматов') ?></div>
                <div class="cat__title"><?= h($title) ?></div>
              </div>
              <span class="cat__go"><svg class="icon"><use href="#i-arrow"/></svg></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section section--panel" aria-labelledby="hits-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Популярное</div>
          <h2 class="h-section" id="hits-title">Ароматы, которые <em>выбирают чаще</em></h2>
        </div>
        <div class="rail-nav">
          <button class="icon-btn" type="button" data-rail="-1" aria-label="Назад"><svg class="icon"><use href="#i-left"/></svg></button>
          <button class="icon-btn" type="button" data-rail="1" aria-label="Вперёд"><svg class="icon"><use href="#i-arrow"/></svg></button>
        </div>
      </div>
      <div class="rail" id="hits"></div>
    </div>
  </section>

  <section class="section" id="catalog" aria-labelledby="catalog-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Каталог</div>
          <h2 class="h-section" id="catalog-title">Все ароматы</h2>
        </div>
        <p>Выберите объём — 5, 10 или 15 мл, цена пересчитается сразу. Товары, которых сейчас нет, можно забронировать.</p>
      </div>

      <div class="catalog">
        <aside class="filters" id="filters" aria-label="Фильтры">
          <div class="filters__head">
            <b>Фильтры</b>
            <button class="close" type="button" data-close="filters" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
          </div>
          <div class="f-group">
            <label class="f-search">
              <svg class="icon"><use href="#i-search"/></svg>
              <span class="visually-hidden">Поиск в каталоге</span>
              <input type="search" id="fSearch" placeholder="Название или бренд">
            </label>
          </div>
          <div class="f-group">
            <div class="f-title">Для кого</div>
            <div class="f-chips" id="fGender">
              <button type="button" class="chip is-active" data-v="">Все</button>
              <button type="button" class="chip" data-v="Женские">Для неё</button>
              <button type="button" class="chip" data-v="Мужские">Для него</button>
              <button type="button" class="chip" data-v="Унисекс">Унисекс</button>
            </div>
          </div>
          <div class="f-group">
            <div class="f-title">Объём</div>
            <div class="f-chips" id="fVolume">
              <button type="button" class="chip is-active" data-v="">Любой</button>
              <button type="button" class="chip" data-v="5">5 мл</button>
              <button type="button" class="chip" data-v="10">10 мл</button>
              <button type="button" class="chip" data-v="15">15 мл</button>
            </div>
          </div>
          <div class="f-group">
            <div class="f-title">Цена, ₽</div>
            <div class="f-range">
              <input type="number" id="fMin" placeholder="от" min="0" step="100" inputmode="numeric" aria-label="Цена от">
              <input type="number" id="fMax" placeholder="до" min="0" step="100" inputmode="numeric" aria-label="Цена до">
            </div>
          </div>
          <div class="f-group">
            <label class="toggle">Только в наличии <input type="checkbox" id="fStock"></label>
            <label class="toggle" style="margin-top:14px">Избранное <input type="checkbox" id="fFav"></label>
          </div>
          <div class="f-group">
            <div class="f-title">Бренд</div>
            <div class="f-brands f-list" id="fBrand">
              <button type="button" class="f-opt is-active" data-v=""><span>Все бренды</span><span><?= count($products) ?></span></button>
              <?php foreach ($brands as $name => $count): ?>
                <button type="button" class="f-opt" data-v="<?= h($name) ?>"><span><?= h($name) ?></span><span><?= $count ?></span></button>
              <?php endforeach; ?>
            </div>
          </div>
          <button type="button" class="f-reset" id="fReset">Сбросить фильтры</button>
        </aside>

        <div>
          <div class="cat-bar">
            <div class="cat-bar__count" id="count"></div>
            <div class="cat-bar__right">
              <button class="btn btn--line btn--sm filters-open" type="button" data-open="filters"><svg class="icon"><use href="#i-filter"/></svg> Фильтры</button>
              <select class="f-select" id="fSort" aria-label="Сортировка">
                <option value="popular">Сначала популярные</option>
                <option value="price_asc">Сначала дешевле</option>
                <option value="price_desc">Сначала дороже</option>
                <option value="name">По названию A–Z</option>
              </select>
            </div>
          </div>
          <div class="active-tags" id="activeTags"></div>
          <div class="products" id="grid" aria-live="polite"></div>
          <div class="more-wrap">
            <small id="moreInfo"></small>
            <div class="progress-line"><i id="moreBar"></i></div>
            <button class="btn btn--line" type="button" id="more">Показать ещё</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--panel" id="promo" aria-labelledby="promo-title">
    <div class="container">
      <div class="promo reveal">
        <div class="promo__media"><img src="/assets/img/gift.webp" alt="Подарочная упаковка Perfume Space" loading="lazy" width="1000" height="750"></div>
        <div class="promo__body">
          <div class="eyebrow">Акции</div>
          <h2 class="h-section" id="promo-title">Больше ароматов — <em>больше выгоды</em></h2>
          <div class="offers">
            <div class="offer">
              <div class="offer__num">2<small> мл</small></div>
              <div><h3><?= h($promos['gift_title'] ?? '') ?></h3><p>Добавим подарок автоматически, когда сумма корзины достигнет <?= format_price($gift_from) ?>.</p></div>
            </div>
            <div class="offer">
              <div class="offer__num">−<?= $bundle_pct ?><small>%</small></div>
              <div><h3>При покупке от <?= $bundle_min ?> позиций</h3><p>Скидка на весь заказ считается в корзине сразу.</p></div>
            </div>
            <div class="offer">
              <div class="offer__num">%</div>
              <div><h3><?= h($promos['loyalty_title'] ?? '') ?></h3><p><?= contact_value($promos['loyalty_desc'] ?? '') ?></p></div>
            </div>
          </div>
          <a href="#catalog" class="btn btn--primary">Собрать набор <svg class="icon icon--arrow"><use href="#i-arrow"/></svg></a>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="brands" aria-labelledby="brands-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Бренды</div>
          <h2 class="h-section" id="brands-title"><?= count($brands) ?> парфюмерных домов</h2>
        </div>
        <p>От Amouage и Creed до Xerjoff и Tom Ford. Нажмите на бренд — каталог покажет его ароматы.</p>
      </div>
      <div class="abc" id="abc">
        <button type="button" class="is-active" data-letter="ALL">Все</button>
        <?php foreach (array_keys($brand_groups) as $l): ?><button type="button" data-letter="<?= h($l) ?>"><?= h($l) ?></button><?php endforeach; ?>
      </div>
      <div class="brand-index">
        <?php foreach ($brand_groups as $l => $items): ?>
          <div class="brand-group" data-group="<?= h($l) ?>">
            <div class="brand-group__letter"><?= h($l) ?></div>
            <?php foreach ($items as $name => $count): ?>
              <button type="button" class="brand-link" data-brand="<?= h($name) ?>"><span><?= h($name) ?></span><span><?= $count ?></span></button>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section section--panel" id="original" aria-labelledby="original-title">
    <div class="container trust">
      <div class="trust__text reveal">
        <div class="eyebrow">Оригинальность</div>
        <h2 class="h-section" id="original-title">Только оригинальная <em>продукция</em></h2>
        <p class="lead"><?= h($config['about_text'] ?? '') ?></p>
        <a href="#request" class="link-arrow">Задать вопрос о товаре <svg class="icon"><use href="#i-arrow"/></svg></a>
      </div>
      <div class="trust__docs reveal">
        <div class="doc"><svg class="icon"><use href="#i-doc"/></svg><h3>Декларация соответствия</h3><p>Номер декларации: <?= contact_value($legal['declaration_number'] ?? '') ?></p></div>
        <div class="doc"><svg class="icon"><use href="#i-code"/></svg><h3>«Честный ЗНАК»</h3><p>Продукция с государственной маркировкой.</p></div>
        <div class="doc"><svg class="icon"><use href="#i-star"/></svg><h3>С 2018 года</h3><p>Рейтинг 5,0 и более 3 500 отзывов покупателей.</p></div>
        <div class="doc"><svg class="icon"><use href="#i-gift"/></svg><h3>Подарочная упаковка</h3><p>Каждый заказ — в подарочной упаковке.</p></div>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="steps-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Как заказать</div>
          <h2 class="h-section" id="steps-title">Четыре шага до <em>нового аромата</em></h2>
        </div>
      </div>
      <div class="steps">
        <div class="step reveal"><h3>Выберите аромат и объём</h3><p>5, 10 или 15 мл. Не уверены — оставьте заявку, поможем с выбором.</p></div>
        <div class="step reveal"><h3>Оформите заказ или бронь</h3><p>Скидка от <?= $bundle_min ?> позиций и подарок от <?= format_price($gift_from) ?> применяются в корзине.</p></div>
        <div class="step reveal"><h3>Подтвердим и пришлём оплату</h3><p>Менеджер проверит наличие и отправит ссылку на оплату удобным способом.</p></div>
        <div class="step reveal"><h3>Отправим в подарочной упаковке</h3><p>Обычно в течение суток. Статус заказа виден в разделе «Мои заказы».</p></div>
      </div>
    </div>
  </section>

  <section class="section section--panel" id="delivery" aria-labelledby="delivery-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Доставка и оплата</div>
          <h2 class="h-section" id="delivery-title">Отправляем <em>по всей России</em></h2>
        </div>
        <p><?= h($config['delivery_text'] ?? '') ?></p>
      </div>
      <div class="info-grid reveal">
        <div class="info-col">
          <h3>Доставка</h3>
          <p>Выберите службу при оформлении заказа.</p>
          <div class="rows">
            <div class="row"><b>СДЭК</b><span>До пункта выдачи или курьером</span></div>
            <div class="row"><b>Почта России</b><span>В любой населённый пункт</span></div>
            <div class="row"><b>Яндекс Доставка</b><span>Пункты выдачи и курьер</span></div>
          </div>
        </div>
        <div class="info-col">
          <h3>Оплата</h3>
          <p>После подтверждения заказа пришлём ссылку на оплату.</p>
          <div class="rows">
            <div class="row"><b>СБП</b><span>Через приложение банка по QR-коду или ссылке</span></div>
            <div class="row"><b>Банковская карта</b><span>Онлайн, с электронным чеком на почту</span></div>
            <div class="row"><b>Бронь</b><span>Закрепим аромат за вами и согласуем оплату с менеджером</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="reviews" aria-labelledby="reviews-title">
    <div class="container reviews">
      <div class="rating">
        <div class="eyebrow">Отзывы</div>
        <h2 class="visually-hidden" id="reviews-title">Отзывы покупателей</h2>
        <div class="rating__big">5,0</div>
        <div class="rating__stars"><?php for ($i = 0; $i < 5; $i++): ?><svg class="icon"><use href="#i-star"/></svg><?php endfor; ?></div>
        <p>Более 3 500 отзывов покупателей</p>
      </div>
      <div class="quotes">
        <?php foreach (($config['reviews'] ?? []) as $rev): ?>
          <figure class="quote reveal">
            <div class="quote__mark">“</div>
            <blockquote class="quote__text"><?= contact_value($rev['text'] ?? '') ?></blockquote>
            <figcaption class="quote__meta"><?= h($rev['author'] ?? '') ?> · <?= h($rev['perfume'] ?? '') ?></figcaption>
          </figure>
        <?php endforeach; ?>
        <figure class="quote reveal">
          <div class="quote__mark">“</div>
          <blockquote class="quote__text">Более 10 000 покупателей с 2018 года.</blockquote>
          <figcaption class="quote__meta">Perfume Space</figcaption>
        </figure>
      </div>
    </div>
  </section>

  <section class="section section--panel" aria-labelledby="faq-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Вопросы</div>
          <h2 class="h-section" id="faq-title">Перед покупкой</h2>
        </div>
      </div>
      <div class="faq">
        <details><summary>Это оригинальная парфюмерия?</summary><p>Да. Оригинальность продукции подтверждена декларацией соответствия и маркировкой «Честный ЗНАК». Если остались вопросы по конкретному аромату — напишите нам, расскажем подробнее.</p></details>
        <details><summary>Что такое распив?</summary><p>Это часть аромата из оригинального флакона, перелитая в атомайзер на 5, 10 или 15 мл. Так можно попробовать и носить дорогой аромат без покупки целого флакона.</p></details>
        <details><summary>Как быстро вы отправляете заказ?</summary><p>Обычно в течение суток после оформления. Доставляем по всей России через СДЭК, Почту России и Яндекс Доставку.</p></details>
        <details><summary>Как получить подарок и скидку?</summary><p>При сумме заказа от <?= format_price($gift_from) ?> добавим парфюм 2 мл в подарок. При покупке от <?= $bundle_min ?> позиций скидка <?= $bundle_pct ?>% применяется ко всему заказу. Корзина считает всё автоматически.</p></details>
        <details><summary>Что делать, если аромата нет в наличии?</summary><p>Нажмите «Забронировать» в карточке — мы сообщим, когда аромат появится, и закрепим его за вами.</p></details>
      </div>
    </div>
  </section>

  <section class="section" id="request" aria-labelledby="request-title">
    <div class="container lead-block">
      <div class="lead-block__text">
        <div class="eyebrow">Заявка</div>
        <h2 class="h-section" id="request-title">Поможем выбрать <em>или забронируем</em></h2>
        <p class="lead">Расскажите, какой аромат ищете, или оставьте контакты — свяжемся с вами в рабочее время: ежедневно <?= h($config['work_hours'] ?? '') ?>.</p>
        <ul class="lead-block__list">
          <li><svg class="icon"><use href="#i-check"/></svg>Подбор аромата под ваш запрос</li>
          <li><svg class="icon"><use href="#i-check"/></svg>Бронь аромата, которого нет в наличии</li>
          <li><svg class="icon"><use href="#i-check"/></svg>Вопросы о заказе и доставке</li>
        </ul>
      </div>
      <form class="form" id="leadForm" novalidate>
        <div class="field">
          <label for="lType">Что нужно</label>
          <select class="input" id="lType" name="request_type">
            <option>Помочь с выбором аромата</option>
            <option>Забронировать аромат</option>
            <option>Сообщить о поступлении</option>
            <option>Вопрос о заказе или доставке</option>
          </select>
        </div>
        <div class="field"><label for="lName">Имя</label><input class="input" id="lName" name="name" autocomplete="name" required></div>
        <div class="form-row">
          <div class="field"><label for="lPhone">Телефон</label><input class="input" id="lPhone" name="phone" type="tel" autocomplete="tel" placeholder="+7" required></div>
          <div class="field"><label for="lEmail">Почта</label><input class="input" id="lEmail" name="email" type="email" autocomplete="email" required></div>
        </div>
        <div class="field"><label for="lPerfume">Аромат или пожелания</label><input class="input" id="lPerfume" name="perfume_name" placeholder="Необязательно"></div>
        <label class="consent"><input type="checkbox" name="consent" required><span>Согласен на обработку персональных данных в соответствии с <a href="/privacy.php" target="_blank">политикой конфиденциальности</a></span></label>
        <button type="submit" class="btn btn--primary btn--block">Отправить заявку</button>
      </form>
    </div>
  </section>

  <section class="section section--panel" id="contacts" aria-labelledby="contacts-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Контакты</div>
          <h2 class="h-section" id="contacts-title">Мы на связи</h2>
        </div>
        <p>Интернет-магазин без офлайн-точки. Работаем ежедневно <?= h($config['work_hours'] ?? '') ?>.</p>
      </div>
      <div class="contacts">
        <div class="contact">
          <h3>Написать нам</h3>
          <?php if (is_filled($c['telegram'] ?? '')): ?><a class="big" href="<?= h($c['telegram']) ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
          <?php if (is_filled($c['vk'] ?? '')): ?><p><a href="<?= h($c['vk']) ?>" target="_blank" rel="noopener">ВКонтакте</a></p><?php endif; ?>
          <?php if (is_filled($email)): ?><p><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></p><?php endif; ?>
        </div>
        <div class="contact">
          <h3>Реквизиты</h3>
          <p><?= h($legal['entity'] ?? '') ?></p>
          <p>ИНН <?= h($legal['inn'] ?? '') ?></p>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer__top">
      <div class="footer__about">
        <a href="/" class="logo"><img src="/assets/img/logo-white.png" alt="Perfume Space" width="56" height="44" loading="lazy"><span class="logo__word"><span class="logo__name">Perfume<br>Space</span></span></a>
        <p>Оригинальная нишевая и люксовая парфюмерия на распив с доставкой по всей России.</p>
      </div>
      <div>
        <h4>Каталог</h4>
        <ul>
          <li><a href="#catalog" data-gender="Женские">Для неё</a></li>
          <li><a href="#catalog" data-gender="Мужские">Для него</a></li>
          <li><a href="#catalog" data-gender="Унисекс">Унисекс</a></li>
          <li><a href="#brands">Бренды</a></li>
          <li><a href="#promo">Акции</a></li>
        </ul>
      </div>
      <div>
        <h4>Покупателям</h4>
        <ul>
          <li><a href="#original">Оригинальность</a></li>
          <li><a href="#delivery">Доставка и оплата</a></li>
          <li><a href="#reviews">Отзывы</a></li>
          <li><a href="#" data-open="orders">Мои заказы</a></li>
        </ul>
      </div>
      <div>
        <h4>Документы</h4>
        <ul>
          <li><a href="/privacy.php">Политика конфиденциальности</a></li>
          <li><a href="/privacy.php#offer">Оферта</a></li>
          <?php if (is_filled($email)): ?><li><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer__bottom">
      <span>© 2018–<?= date('Y') ?> Perfume Space · <?= h($legal['entity'] ?? '') ?>, ИНН <?= h($legal['inn'] ?? '') ?></span>
      <span>Фотографии категорий носят иллюстративный характер</span>
    </div>
  </div>
</footer>

<div class="overlay" id="overlay"></div>

<aside class="drawer" id="cart" aria-label="Корзина" aria-hidden="true">
  <div class="drawer__head">
    <div class="drawer__title">Корзина<sup id="cartQty"></sup></div>
    <button class="close" type="button" data-close="cart" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
  </div>
  <div class="drawer__body" id="cartBody"></div>
  <div class="drawer__foot" id="cartFoot"></div>
</aside>

<aside class="drawer" id="favs" aria-label="Избранное" aria-hidden="true">
  <div class="drawer__head">
    <div class="drawer__title">Избранное<sup id="favQty"></sup></div>
    <button class="close" type="button" data-close="favs" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
  </div>
  <div class="drawer__body" id="favBody"></div>
</aside>

<aside class="drawer" id="orders" aria-label="Мои заказы" aria-hidden="true">
  <div class="drawer__head">
    <div class="drawer__title">Мои заказы</div>
    <button class="close" type="button" data-close="orders" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
  </div>
  <div class="drawer__body">
    <div class="tabs" id="orderTabs">
      <button type="button" class="is-active" data-v="active">Активные</button>
      <button type="button" data-v="done">Завершённые</button>
    </div>
    <div id="ordersList"></div>
  </div>
</aside>

<aside class="drawer" id="notes" aria-label="Уведомления" aria-hidden="true">
  <div class="drawer__head">
    <div class="drawer__title">Уведомления</div>
    <button class="close" type="button" data-close="notes" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
  </div>
  <div class="drawer__body" id="notesList"></div>
</aside>

<div class="modal" id="product" role="dialog" aria-modal="true" aria-label="Карточка аромата">
  <div class="modal__box">
    <button class="close" type="button" data-close="product" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
    <div id="productBody"></div>
  </div>
</div>

<div class="cookie" id="cookie" role="region" aria-label="Cookie">
  <span>Мы используем cookie, чтобы сохранять корзину и избранное, и Яндекс Метрику для статистики. Подробнее — в <a href="/privacy.php">политике конфиденциальности</a>.</span>
  <button class="btn btn--light btn--sm" type="button" id="cookieOk">Хорошо</button>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>

<script>
  window.PS = {
    products: <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    giftFrom: <?= $gift_from ?>,
    bundleMin: <?= $bundle_min ?>,
    bundlePct: <?= $bundle_pct ?>
  };
</script>
<script src="/assets/js/app.js?v=4" defer></script>
</body>
</html>
