<?php
require_once __DIR__ . '/includes/layout.php';
handle_legacy_redirects();

$config = get_site_config();
$products = catalog();
$brands = brand_list();

$brand_groups = [];
foreach ($brands as $name => $count) {
    $letter = strtoupper(substr($name, 0, 1));
    $brand_groups[ctype_alpha($letter) ? $letter : '#'][$name] = $count;
}

$hits = array_values(array_filter($products, fn($p) => !empty($p['is_hit']) && !empty($p['in_stock'])));
$news_col = collection_by_slug('new-perfumes');
$news = array_slice(array_filter(array_map(fn($id) => $products[$id] ?? null, $news_col ? collection_ids($news_col) : [])), 0, 16);
$home_collections = [];
foreach (collections() as $col) {
    if (empty($col['home_block'])) continue;
    $items = array_slice(array_filter(array_map(fn($id) => $products[$id] ?? null, collection_ids($col))), 0, 16);
    if ($items) $home_collections[] = [$col, array_values($items)];
}
$promo_codes = array_values(array_filter($config['promo_codes'] ?? [], fn($code) => !empty($code['active']) && !empty($code['code']) && !empty($code['show_on_home'])));
$published_reviews = array_slice(array_reverse(array_values(array_filter(get_reviews(), fn($r) => ($r['status'] ?? '') === 'published'))), 0, 8);

$c = $config['contacts'] ?? [];
$legal = $config['legal'] ?? [];
$promos = $config['promos'] ?? [];
$gift_from = (int)($promos['gift_threshold'] ?? 8000);
$bundle_min = (int)($promos['bundle_min_items'] ?? 5);
$bundle_pct = (int)($promos['bundle_discount_percent'] ?? 10);
$site = site_url();
$email = $c['email'] ?? '';

function contact_value(?string $value, bool $link = false): string {
    if (!is_filled($value)) {
        return '<span class="tbd">' . h($value ?: '[заполнить]') . '</span>';
    }
    return $link ? '<a href="' . h($value) . '" target="_blank" rel="noopener">' . h($value) . '</a>' : h($value);
}

layout_start([
    'page' => 'home',
    'title' => 'Perfume Space — оригинальная парфюмерия на распив от 5 мл',
    'description' => 'Оригинальная нишевая и люксовая парфюмерия на распив 5, 10 и 15 мл: более 1 000 ароматов, 90 брендов. Декларация соответствия, «Честный ЗНАК», бесплатная доставка по России.',
    'canonical' => '/',
    'head_extra' => '<link rel="preload" as="image" href="/assets/img/hero.webp" fetchpriority="high">',
    'jsonld' => [[
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
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'url' => $site . '/',
        'potentialAction' => ['@type' => 'SearchAction', 'target' => $site . '/?q={search_term_string}#catalog', 'query-input' => 'required name=search_term_string'],
    ]],
]);
?>

<main>
  <section class="hero">
    <div class="hero__bg"><img src="/assets/img/hero.webp" alt="" width="1920" height="1080" fetchpriority="high"></div>
    <div class="container hero__content">
      <div class="hero__text">
        <?php if ($promo_codes): ?>
        <div class="slides" id="slides">
          <?php foreach ($promo_codes as $code): ?>
            <article class="slide">
              <div class="slide__body">
                <div class="slide__badge"><?= h($code['title'] ?? '') ?></div>
                <h2><?= h($code['lead'] ?? '') ?></h2>
                <p><?= h($code['text'] ?? '') ?></p>
                <button type="button" class="slide__code" data-copy="<?= h($code['code']) ?>"><?= h($code['code']) ?> <svg class="icon"><use href="#i-doc"/></svg></button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <h1 class="h-display"><?= h($config['hero_title'] ?? '') ?></h1>
        <p class="lead"><?= h($config['hero_subtitle'] ?? '') ?></p>
        <div class="hero__ctas">
          <a href="#catalog" class="btn btn--primary" data-goal="hero_catalog">Выбрать аромат <svg class="icon icon--arrow"><use href="#i-arrow"/></svg></a>
        </div>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="cats-title">
    <div class="container">
      <div class="section-head">
        <div class="eyebrow" id="cats-title">Категории</div>
        <a href="#catalog" class="link-arrow">Весь каталог <svg class="icon"><use href="#i-arrow"/></svg></a>
      </div>
      <div class="cats">
        <?php foreach ([['/category/women_parfum/', 'Для неё', 'cat-women'], ['/category/men_parfum/', 'Для него', 'cat-men'], ['/category/unisex/', 'Унисекс', 'cat-unisex']] as [$url, $title, $img]): ?>
          <a href="<?= $url ?>" class="cat reveal">
            <img src="/assets/img/<?= $img ?>.webp" alt="<?= h($title) ?> — парфюмерия на распив" loading="lazy" width="720" height="960">
            <div class="cat__body">
              <div>
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
        <?= rail_nav('hits') ?>
      </div>
      <?php rail_block('hits', $hits); ?>
    </div>
  </section>

  <?php foreach ($home_collections as [$col, $items]): ?>
  <section class="section section--feature" aria-label="<?= h($col['title']) ?>">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Наша подборка</div>
          <h2 class="h-section"><a href="<?= h(collection_url($col)) ?>"><?= h($col['title']) ?></a></h2>
          <?php if (!empty($col['intro'])): ?><p class="section-head__sub"><?= h($col['intro']) ?></p><?php endif; ?>
        </div>
        <div class="section-head__side">
          <a href="<?= h(collection_url($col)) ?>" class="link-arrow">Вся подборка <svg class="icon"><use href="#i-arrow"/></svg></a>
          <?= rail_nav('col-' . $col['slug']) ?>
        </div>
      </div>
      <?php rail_block('col-' . $col['slug'], $items); ?>
    </div>
  </section>
  <?php endforeach; ?>

  <section class="section section--panel" aria-labelledby="new-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Новинки</div>
          <h2 class="h-section" id="new-title">Новые <em>поступления</em></h2>
        </div>
        <div class="section-head__side">
          <a href="/category/new-perfumes/" class="link-arrow">Все новинки <svg class="icon"><use href="#i-arrow"/></svg></a>
          <?= rail_nav('news') ?>
        </div>
      </div>
      <?php rail_block('news', $news); ?>
    </div>
  </section>

  <section class="section" id="catalog" aria-labelledby="catalog-title">
    <div class="container">
      <div class="section-head">
        <div>
          <div class="eyebrow">Каталог</div>
          <h2 class="h-section" id="catalog-title">Все ароматы</h2>
        </div>
      </div>

      <?php catalog_block(); ?>
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
              <div><h3>При покупке от <?= $bundle_min ?> позиций</h3><p>Скидка применяется автоматически.</p></div>
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
        <a href="/brands/" class="link-arrow">Все бренды <svg class="icon"><use href="#i-arrow"/></svg></a>
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
              <a class="brand-link" href="<?= h(brand_url($name)) ?>"><span><?= h($name) ?></span><span><?= $count ?></span></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
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
        <h3>Бесплатная доставка</h3>
        <p>По всей России. Обычно отправляем заказ в течение суток.</p>
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

  <section class="section section--panel" id="original" aria-labelledby="original-title">
    <div class="container trust">
      <div class="trust__text reveal">
        <div class="eyebrow">Оригинальность</div>
        <h2 class="h-section" id="original-title">Только оригинальная <em>продукция</em></h2>
        <p class="lead"><?= h($config['about_text'] ?? '') ?></p>
        <div class="hero__ctas"><a href="/original/" class="btn btn--line">Гарантия оригинальности <svg class="icon icon--arrow"><use href="#i-arrow"/></svg></a><button type="button" class="link-arrow" data-ask="1">Задать вопрос <svg class="icon"><use href="#i-arrow"/></svg></button></div>
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
        <div class="step reveal"><h3>Выберите аромат и объём</h3><p>5, 10 или 15 мл. Цена пересчитывается сразу.</p></div>
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
          <h2 class="h-section" id="delivery-title">Бесплатная доставка <em>по всей России</em></h2>
        </div>
        <p><?= h($config['delivery_text'] ?? '') ?></p>
      </div>
      <div class="info-grid reveal">
        <div class="info-col">
          <h3>Доставка — бесплатно</h3>
          <p>Выберите службу при оформлении заказа. <a href="/dostavka/" class="u-link">Подробнее</a></p>
          <div class="rows">
            <div class="row"><b>СДЭК</b><span>До пункта выдачи или курьером</span></div>
            <div class="row"><b>Почта России</b><span>В любой населённый пункт</span></div>
            <div class="row"><b>Яндекс Доставка</b><span>Пункты выдачи и курьер</span></div>
          </div>
        </div>
        <div class="info-col">
          <h3>Оплата</h3>
          <p>После подтверждения заказа пришлём ссылку на оплату. <a href="/oplata/" class="u-link">Подробнее</a></p>
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
        <?php foreach ($published_reviews as $rev): ?>
          <figure class="quote reveal">
            <div class="quote__mark">“</div>
            <blockquote class="quote__text"><?= h($rev['text'] ?? '') ?></blockquote>
            <figcaption class="quote__meta"><?= stars_html((float)($rev['rating'] ?? 5)) ?> <?= h($rev['author'] ?? '') ?><?php $rp = catalog()[(int)($rev['product_id'] ?? 0)] ?? null; if ($rp): ?> · <a href="<?= h(product_url($rp)) ?>"><?= h($rp['name']) ?></a><?php elseif (!empty($rev['perfume'])): ?> · <?= h($rev['perfume']) ?><?php endif; ?></figcaption>
          </figure>
        <?php endforeach; ?>
        <form class="form quote-form" id="reviewForm" novalidate>
          <h3 class="h-section" style="font-size:32px">Оставить отзыв</h3>
          <div class="field"><label for="rName">Имя</label><input class="input" id="rName" name="name" required></div>
          <div class="field"><label for="rPerfume">Аромат</label><input class="input" id="rPerfume" name="perfume" placeholder="Начните вводить название — необязательно" list="rPerfumeList" autocomplete="off"><datalist id="rPerfumeList"></datalist></div>
          <div class="field"><label for="rRating">Оценка</label><select class="input" id="rRating" name="rating"><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select></div>
          <div class="field"><label for="rText">Отзыв</label><textarea class="input" id="rText" name="text" rows="4" required></textarea></div>
          <label class="consent"><input type="checkbox" name="consent" required><span>Согласен на публикацию отзыва после проверки</span></label>
          <button class="btn btn--primary" type="submit">Отправить</button>
        </form>
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
        <details><summary>Сколько стоит доставка?</summary><p>Доставка по всей России бесплатная. Обычно отправляем заказ в течение суток после оформления — через СДЭК, Почту России или Яндекс Доставку.</p></details>
        <details><summary>Как получить подарок и скидку?</summary><p>При сумме заказа от <?= format_price($gift_from) ?> выберите парфюм 2 мл в подарок прямо в корзине. При покупке от <?= $bundle_min ?> позиций скидка <?= $bundle_pct ?>% применяется автоматически.</p></details>
        <details><summary>Что делать, если аромата нет в наличии?</summary><p>Нажмите «Забронировать» на странице аромата — мы сообщим, когда аромат появится, и закрепим его за вами.</p></details>
      </div>
    </div>
  </section>

  <section class="section" id="request" aria-labelledby="request-title">
    <div class="container lead-block">
      <div class="lead-block__text">
        <div class="eyebrow">Заявка</div>
        <h2 class="h-section" id="request-title">Бронь <em>и вопросы по заказу</em></h2>
        <p class="lead">Оставьте контакты — свяжемся с вами в рабочее время: ежедневно <?= h($config['work_hours'] ?? '') ?>.</p>
        <ul class="lead-block__list">
          <li><svg class="icon"><use href="#i-check"/></svg>Бронь аромата, которого нет в наличии</li>
          <li><svg class="icon"><use href="#i-check"/></svg>Вопросы о заказе и доставке</li>
        </ul>
      </div>
      <?php include __DIR__ . '/includes/views/lead-form.php'; ?>
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
          <p><a href="/rekvizity/">Все реквизиты</a></p>
        </div>
      </div>
    </div>
  </section>
</main>

<?php layout_end(['page' => 'home']);
