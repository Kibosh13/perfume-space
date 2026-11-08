<?php
/**
 * Общий каркас страниц: <head>, бегущая строка, шапка с меню, подвал, корзина и панели.
 */
require_once __DIR__ . '/catalog.php';

function site_url(): string {
    return rtrim(get_site_config()['domain'] ?? 'https://perfume-space.ru', '/');
}

function catalog_js_version(): string {
    $t = 0;
    foreach ([PRODUCTS_FILE, REVIEWS_FILE, BRANDS_FILE, ORDERS_FILE] as $f) {
        if (is_file($f)) $t = max($t, filemtime($f));
    }
    return (string)$t;
}

function info_pages(): array {
    $pages = json_load(PAGES_FILE, []);
    usort($pages, fn($a, $b) => ((int)($a['sort'] ?? 100)) <=> ((int)($b['sort'] ?? 100)));
    return $pages;
}

function page_by_slug(string $slug): ?array {
    foreach (info_pages() as $p) {
        if (($p['slug'] ?? '') === $slug) return $p;
    }
    return null;
}

function layout_start(array $m): void {
    $config = get_site_config();
    $site = site_url();
    $promos = $config['promos'] ?? [];
    $gift_from = (int)($promos['gift_threshold'] ?? 8000);
    $bundle_min = (int)($promos['bundle_min_items'] ?? 5);
    $bundle_pct = (int)($promos['bundle_discount_percent'] ?? 10);
    $metrika_id = $config['integrations']['yandex_metrika_id'] ?? '';
    $webmaster = $config['integrations']['yandex_webmaster_code'] ?? '';
    $c = $config['contacts'] ?? [];
    $legal = $config['legal'] ?? [];
    $title = $m['title'] ?? 'Perfume Space';
    $desc = $m['description'] ?? '';
    $canonical = $m['canonical'] ?? '';
    $og_image = $m['og_image'] ?? ($site . '/assets/img/og-cover.jpg');
    if ($og_image && str_starts_with($og_image, '/')) $og_image = $site . $og_image;
    $menu = collections_menu();
    $is_home = ($m['page'] ?? '') === 'home';
    $home = $is_home ? '' : '/';
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <meta name="description" content="<?= h($desc) ?>">
  <meta name="robots" content="<?= h($m['robots'] ?? 'index, follow') ?>">
  <?php if ($canonical): ?><link rel="canonical" href="<?= h($site . $canonical) ?>"><?php endif; ?>
  <?php if (is_filled($webmaster)): ?><meta name="yandex-verification" content="<?= h($webmaster) ?>"><?php endif; ?>
  <meta name="theme-color" content="#05070c">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link rel="apple-touch-icon" href="/assets/img/favicon.png">
  <meta property="og:type" content="<?= h($m['og_type'] ?? 'website') ?>">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:site_name" content="Perfume Space">
  <meta property="og:title" content="<?= h($m['og_title'] ?? $title) ?>">
  <meta property="og:description" content="<?= h($desc) ?>">
  <?php if ($canonical): ?><meta property="og:url" content="<?= h($site . $canonical) ?>"><?php endif; ?>
  <meta property="og:image" content="<?= h($og_image) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?= $m['head_extra'] ?? '' ?>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
  <?php foreach ((array)($m['jsonld'] ?? []) as $ld): ?>
  <script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endforeach; ?>
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
<body class="<?= h($m['body_class'] ?? '') ?>">
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
  <symbol id="i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
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
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5 19.5c1.4-3 3.8-4.5 7-4.5s5.6 1.5 7 4.5"/></symbol>
</svg>

<div class="topbar">
  <div class="container topbar__inner">
    <div class="topbar__track">
      <?php for ($i = 0; $i < 2; $i++): ?>
        <span>Бесплатная доставка по всей России</span>
        <span>Парфюм 2 мл в подарок при заказе от <?= format_price($gift_from) ?></span>
        <span>Скидка <?= $bundle_pct ?>% от <?= $bundle_min ?> позиций в заказе</span>
        <span>Ежедневно <?= h($config['work_hours'] ?? '') ?></span>
      <?php endfor; ?>
    </div>
  </div>
</div>

<header class="header">
  <div class="container header__inner">
    <a href="/" class="logo" aria-label="Perfume Space — на главную">
      <img src="/assets/img/logo-white.png" alt="Perfume Space" width="56" height="44">
      <span class="logo__word"><span class="logo__name">Perfume<br>Space</span></span>
    </a>

    <nav class="nav" id="nav" aria-label="Основное меню">
      <div class="nav__item nav__item--mega">
        <a href="<?= $home ?>#catalog" class="nav__link">Каталог <svg class="icon"><use href="#i-down"/></svg></a>
        <div class="mega">
          <div class="container mega__inner">
            <?php foreach ($menu as $group => $items): ?>
              <div class="mega__col">
                <div class="mega__title"><?= h($group) ?></div>
                <?php foreach ($items as $col): ?><a href="<?= h(collection_url($col)) ?>"><?= h($col['title']) ?></a><?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <a href="/brands/">Бренды</a>
      <a href="/category/">Подборки</a>
      <a href="<?= $home ?>#promo">Акции</a>
      <a href="/original/">Оригинальность</a>
      <a href="/dostavka/">Доставка</a>
      <a href="<?= $home ?>#contacts">Контакты</a>
    </nav>

    <div class="tools">
      <button class="tool" type="button" data-open="search" aria-label="Поиск"><svg class="icon"><use href="#i-search"/></svg></button>
      <button class="tool" type="button" data-open="account" aria-label="Личный кабинет"><svg class="icon"><use href="#i-user"/></svg></button>
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
        <input type="search" id="searchInput" placeholder="Бренд или название, например «баккара»" autocomplete="off" aria-label="Поиск по каталогу">
        <button class="close" type="button" data-close="search" aria-label="Закрыть поиск"><svg class="icon"><use href="#i-close"/></svg></button>
      </div>
      <div class="search-results" id="searchResults"></div>
    </div>
  </div>
</header>
<?php
}

function layout_end(array $js = []): void {
    $config = get_site_config();
    $promos = $config['promos'] ?? [];
    $c = $config['contacts'] ?? [];
    $legal = $config['legal'] ?? [];
    $email = $c['email'] ?? '';
    $pages = info_pages();
    $public_codes = array_values(array_filter($config['promo_codes'] ?? [], fn($p) => !empty($p['active']) && !empty($p['show_on_home'])));
    $ps = array_merge([
        'page' => 'page',
        'base' => '',
        'giftFrom' => (int)($promos['gift_threshold'] ?? 8000),
        'bundleMin' => (int)($promos['bundle_min_items'] ?? 5),
        'bundlePct' => (int)($promos['bundle_discount_percent'] ?? 10),
        'loyaltyPct' => (int)($promos['loyalty_percent'] ?? 1),
        'loyaltyMax' => (int)($promos['loyalty_max_share'] ?? 50),
        'aliases' => brand_aliases(),
        'brandSlugs' => array_combine(array_keys(brand_list()), array_map(fn($b) => brand_info($b)['slug'], array_keys(brand_list()))),
        'accords' => ACCORDS,
        'seasons' => SEASONS,
    ], $js);
    ?>
<footer class="footer">
  <div class="container">
    <div class="footer__top">
      <div class="footer__about">
        <a href="/" class="logo"><img src="/assets/img/logo-white.png" alt="Perfume Space" width="56" height="44" loading="lazy"><span class="logo__word"><span class="logo__name">Perfume<br>Space</span></span></a>
        <p>Оригинальная нишевая и люксовая парфюмерия на распив. Бесплатная доставка по всей России.</p>
      </div>
      <div>
        <h4>Каталог</h4>
        <ul>
          <li><a href="/category/women_parfum/">Женская</a></li>
          <li><a href="/category/men_parfum/">Мужская</a></li>
          <li><a href="/category/unisex/">Унисекс</a></li>
          <li><a href="/category/new-perfumes/">Новинки</a></li>
          <li><a href="/category/bestsellers/">Хиты продаж</a></li>
          <li><a href="/brands/">Бренды</a></li>
          <li><a href="/category/">Все подборки</a></li>
        </ul>
      </div>
      <div>
        <h4>Покупателям</h4>
        <ul>
          <?php foreach ($pages as $p): if (($p['group'] ?? '') !== 'Покупателям') continue; ?>
            <li><a href="/<?= h($p['slug']) ?>/"><?= h($p['title']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="#" data-open="orders">Мои заказы</a></li>
        </ul>
      </div>
      <div>
        <h4>Документы</h4>
        <ul>
          <?php foreach ($pages as $p): if (($p['group'] ?? '') !== 'Документы') continue; ?>
            <li><a href="/<?= h($p['slug']) ?>/"><?= h($p['title']) ?></a></li>
          <?php endforeach; ?>
          <?php if (is_filled($email)): ?><li><a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer__bottom">
      <span>© 2018–<?= date('Y') ?> Perfume Space · <?= h($legal['entity'] ?? '') ?>, ИНН <?= h($legal['inn'] ?? '') ?></span>
      <span>Фотографии носят иллюстративный характер</span>
    </div>
  </div>
</footer>

<?php if (($ps['page'] ?? '') !== 'home'): ?>
<section class="section section--tight request-pop" id="request" aria-labelledby="request-title" hidden>
  <div class="container lead-block">
    <div class="lead-block__text">
      <div class="eyebrow">Заявка</div>
      <h2 class="h-section" id="request-title">Бронь <em>и вопросы по заказу</em></h2>
      <p class="lead">Оставьте контакты — сообщим о поступлении аромата и закрепим его за вами. Работаем ежедневно <?= h($config['work_hours'] ?? '') ?>.</p>
    </div>
    <?php include __DIR__ . '/views/lead-form.php'; ?>
  </div>
</section>
<?php endif; ?>

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

<aside class="drawer" id="account" aria-label="Личный кабинет" aria-hidden="true">
  <div class="drawer__head">
    <div class="drawer__title">Кабинет</div>
    <button class="close" type="button" data-close="account" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
  </div>
  <div class="drawer__body" id="accountBody"></div>
</aside>

<aside class="drawer" id="notes" aria-label="Уведомления" aria-hidden="true">
  <div class="drawer__head">
    <div class="drawer__title">Уведомления</div>
    <button class="close" type="button" data-close="notes" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button>
  </div>
  <div class="drawer__body" id="notesList"></div>
</aside>

<button class="ask" type="button" data-ask="1" aria-label="Задать вопрос"><svg class="icon"><use href="#i-chat"/></svg></button>
<div class="ask-box" id="askBox" hidden>
  <div class="ask-box__head">Вопрос<button type="button" data-ask="0" aria-label="Закрыть"><svg class="icon"><use href="#i-close"/></svg></button></div>
  <div class="ask-box__log" id="askLog"></div>
  <form class="ask-box__form" id="askForm">
    <input class="input" name="q" placeholder="Спросите об аромате" autocomplete="off" required>
    <button class="btn btn--primary btn--sm" type="submit">Отправить</button>
  </form>
</div>

<div class="cookie" id="cookie" role="region" aria-label="Cookie">
  <span>Мы используем cookie, чтобы сохранять корзину и избранное, и Яндекс Метрику для статистики. Подробнее — в <a href="/privacy/">политике конфиденциальности</a>.</span>
  <button class="btn btn--light btn--sm" type="button" id="cookieOk">Хорошо</button>
</div>

<div class="toasts" id="toasts" aria-live="polite"></div>

<script>window.PS = <?= json_encode($ps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/catalog.js?v=<?= catalog_js_version() ?>" defer></script>
<script src="/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>" defer></script>
</body>
</html>
<?php
}

/** Блок каталога с фильтрами. $scope — ограничение (бренд/подборка), $title — заголовок */
function catalog_block(array $opt = []): void {
    $ids = $opt['ids'] ?? null;
    $cat = catalog();
    $scope = $ids === null ? $cat : array_filter(array_map(fn($id) => $cat[$id] ?? null, $ids));
    $brands = [];
    foreach ($scope as $p) $brands[$p['brand']] = ($brands[$p['brand']] ?? 0) + 1;
    ksort($brands, SORT_NATURAL | SORT_FLAG_CASE);
    $hide_brand = !empty($opt['hide_brand']);
    $initial = array_values($scope);
    usort($initial, fn($a, $b) => (!empty($b['in_stock']) <=> !empty($a['in_stock'])) ?: popularity($b) <=> popularity($a) ?: (!empty($b['image']) <=> !empty($a['image'])) ?: $a['id'] <=> $b['id']);
    $initial = array_slice($initial, 0, 24);
    ?>
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
        <div class="f-title">Пол</div>
        <div class="f-chips" id="fGender">
          <button type="button" class="chip is-active" data-v="">Все</button>
          <button type="button" class="chip" data-v="Женские">Женский</button>
          <button type="button" class="chip" data-v="Мужские">Мужской</button>
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
        <div class="f-title">Сезон</div>
        <div class="f-chips" id="fSeason"></div>
      </div>
      <div class="f-group">
        <div class="f-title">Основные аккорды</div>
        <div class="f-chips f-chips--multi" id="fAccord"></div>
      </div>
      <?php if (!$hide_brand): ?>
      <div class="f-group">
        <div class="f-title">Бренд</div>
        <div class="f-brands f-list" id="fBrand">
          <button type="button" class="f-opt is-active" data-v=""><span>Все бренды</span><span><?= count($scope) ?></span></button>
          <?php foreach ($brands as $name => $count): ?>
            <button type="button" class="f-opt" data-v="<?= h($name) ?>"><span><?= h($name) ?></span><span><?= $count ?></span></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <button type="button" class="f-reset" id="fReset">Сбросить фильтры</button>
    </aside>

    <div>
      <div class="cat-bar">
        <div class="cat-bar__count" id="count">Найдено <b><?= count($scope) ?></b> <?= plural_ru(count($scope), 'аромат', 'аромата', 'ароматов') ?></div>
        <div class="cat-bar__right">
          <button class="btn btn--line btn--sm filters-open" type="button" data-open="filters"><svg class="icon"><use href="#i-filter"/></svg> Фильтры</button>
          <select class="f-select" id="fSort" aria-label="Сортировка">
            <option value="popular">Сначала популярные</option>
            <option value="new">Новинки</option>
            <option value="price_asc">Цена по возрастанию</option>
            <option value="price_desc">Цена по убыванию</option>
            <option value="rating">Высокий рейтинг</option>
            <option value="name">По названию A–Z</option>
          </select>
        </div>
      </div>
      <div class="active-tags" id="activeTags"></div>
      <div class="products" id="grid" aria-live="polite">
        <?php if ($initial): foreach ($initial as $p) echo card_html($p); else: ?>
          <div class="empty"><h3>Подборка наполняется</h3><p>Скоро здесь появятся ароматы. А пока загляните в <a href="/#catalog" class="link-arrow">общий каталог</a></p></div>
        <?php endif; ?>
      </div>
      <div class="more-wrap">
        <small id="moreInfo"></small>
        <div class="progress-line"><i id="moreBar"></i></div>
        <button class="btn btn--line" type="button" id="more" <?= count($scope) > 24 ? '' : 'style="display:none"' ?>>Показать ещё</button>
      </div>
    </div>
  </div>
<?php
}

/** Горизонтальная лента карточек */
function rail_block(string $id, array $products): void {
    ?>
  <div class="rail" id="<?= h($id) ?>"><?php foreach ($products as $p) echo card_html($p); ?></div>
<?php
}

function rail_nav(string $target): string {
    return '<div class="rail-nav"><button class="icon-btn" type="button" data-rail="-1" data-target="' . h($target) . '" aria-label="Назад"><svg class="icon"><use href="#i-left"/></svg></button><button class="icon-btn" type="button" data-rail="1" data-target="' . h($target) . '" aria-label="Вперёд"><svg class="icon"><use href="#i-arrow"/></svg></button></div>';
}
