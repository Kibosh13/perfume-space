<?php
/** @var array $product */
$p = $product;
$site = site_url();
$config = get_site_config();
$gift_from = (int)($config['promos']['gift_threshold'] ?? 8000);
$title = product_title($p);
$images = product_images($p);
$stats = review_stats()[(int)$p['id']] ?? ['avg' => 0, 'count' => 0];
$reviews = product_reviews((int)$p['id']);
$country = product_country($p);
$min = product_min_price($p);
$url = product_url($p);

$by_users = similar_by_users($p);
$by_notes = similar_by_notes($p);
$also = also_chosen($p);
$brand_more = same_brand($p);

$volumes = [];
foreach (['5', '10', '15'] as $v) {
    if (!empty($p['volumes'][$v])) $volumes[$v] = product_price($p, $v);
}

$facts = array_filter([
    'Парфюмер' => $p['perfumer'],
    'Страна бренда' => $country,
    'Пол' => GENDER_LABELS[$p['gender']] ?? $p['gender'],
    'Год выпуска' => $p['year'] ?: '',
    'Концентрация' => $p['concentration'],
    'Сезонность' => implode(', ', $p['seasons']),
    'Время' => implode(' / ', $p['daytime']),
], fn($v) => $v !== '' && $v !== null);
$pyramid = array_filter(['Верхние ноты' => $p['notes']['top'], 'Средние ноты' => $p['notes']['heart'], 'Базовые ноты' => $p['notes']['base']]);

$meta_title = $p['meta_title'] ?: "{$p['name']} — купить на распив от " . format_price($min) . ' | Perfume Space';
$meta_desc = $p['meta_description'] ?: "{$p['name']}: оригинальный аромат на распив 5, 10 и 15 мл" . ($p['accords'] ? ', аккорды: ' . mb_strtolower(implode(', ', array_slice($p['accords'], 0, 4))) : '') . '. ' . ($p['in_stock'] ? 'В наличии. ' : '') . 'Бесплатная доставка по России, подарочная упаковка.';

$offers = [];
foreach ($volumes as $v => $price) {
    $offers[] = ['@type' => 'Offer', 'name' => "{$v} мл", 'price' => $price, 'priceCurrency' => 'RUB', 'availability' => $p['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock', 'url' => $site . $url];
}
$ld = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $p['name'],
    'brand' => ['@type' => 'Brand', 'name' => $p['brand']],
    'sku' => (string)$p['id'],
    'url' => $site . $url,
    'image' => array_map(fn($i) => $site . $i, $images ?: ['/assets/img/atomizer.webp']),
    'description' => $p['description'] ?: $meta_desc,
    'offers' => $offers ? ['@type' => 'AggregateOffer', 'lowPrice' => min($volumes), 'highPrice' => max($volumes), 'priceCurrency' => 'RUB', 'offerCount' => count($offers), 'offers' => $offers] : null,
];
if ($stats['count']) {
    $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $stats['avg'], 'reviewCount' => $stats['count'], 'bestRating' => 5];
    $ld['review'] = array_map(fn($r) => ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => $r['author']], 'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int)$r['rating']], 'reviewBody' => $r['text']], array_slice($reviews, 0, 5));
}
$ld = array_filter($ld);

layout_start([
    'page' => 'product',
    'title' => $meta_title,
    'description' => $meta_desc,
    'canonical' => $url,
    'og_type' => 'product',
    'og_title' => $p['name'],
    'og_image' => $images[0] ?? '/assets/img/og-cover.jpg',
    'jsonld' => [$ld],
    'body_class' => 'is-product',
]);

$tabs = [];
if ($by_users) $tabs['users'] = ['По мнению пользователей', $by_users];
if ($by_notes) $tabs['notes'] = ['По аккордам и нотам', $by_notes];
if ($also) $tabs['liked'] = ["Тем, кому нравится {$title}, ещё выбирают", $also];
?>
<main class="pd" data-product="<?= (int)$p['id'] ?>">
  <div class="container">
    <?= breadcrumbs_html([[$p['brand'], brand_url($p['brand'])], [$title, '']], $site) ?>

    <div class="pd__top">
      <div class="gallery" id="gallery">
        <div class="gallery__track" id="galleryTrack">
          <?php if ($images): foreach ($images as $i => $img): ?>
            <figure class="gallery__slide"><img src="<?= h($img) ?>" alt="<?= h($p['name']) ?><?= $i ? ' — фото ' . ($i + 1) : '' ?>" width="900" height="900" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>></figure>
          <?php endforeach; else: ?>
            <figure class="gallery__slide gallery__slide--empty"><img src="/assets/img/atomizer.webp" alt="" width="900" height="900"><figcaption><?= h($p['brand']) ?></figcaption></figure>
          <?php endif; ?>
        </div>
        <div class="card__badges">
          <?php if (!empty($p['discount']) && $p['in_stock']): ?><span class="badge badge--sale">−<?= (int)$p['discount'] ?>%</span><?php endif; ?>
          <?php if (!empty($p['is_hit']) && $p['in_stock']): ?><span class="badge badge--hit">Хит</span><?php endif; ?>
          <?php if (in_array('new', $p['tags'], true)): ?><span class="badge">Новинка</span><?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <button class="gallery__arrow gallery__arrow--prev" type="button" data-gallery="-1" aria-label="Предыдущее фото"><svg class="icon"><use href="#i-left"/></svg></button>
          <button class="gallery__arrow gallery__arrow--next" type="button" data-gallery="1" aria-label="Следующее фото"><svg class="icon"><use href="#i-arrow"/></svg></button>
          <div class="gallery__dots"><?php foreach ($images as $i => $img): ?><button type="button" data-slide="<?= $i ?>" class="<?= $i ? '' : 'is-active' ?>" aria-label="Фото <?= $i + 1 ?>"></button><?php endforeach; ?></div>
          <div class="gallery__thumbs"><?php foreach ($images as $i => $img): ?><button type="button" data-slide="<?= $i ?>" class="<?= $i ? '' : 'is-active' ?>"><img src="<?= h($img) ?>" alt="" loading="lazy" width="80" height="80"></button><?php endforeach; ?></div>
        <?php endif; ?>
      </div>

      <div class="pd__info">
        <a href="<?= h(brand_url($p['brand'])) ?>" class="pd__brand"><?= h($p['brand']) ?></a>
        <h1 class="pd__name"><?= h($p['name']) ?></h1>
        <a href="#reviews" class="pd__rating">
          <?= stars_html((float)$stats['avg']) ?>
          <span><?= $stats['count'] ? str_replace('.', ',', (string)$stats['avg']) . ' · ' . $stats['count'] . ' ' . plural_ru($stats['count'], 'отзыв', 'отзыва', 'отзывов') : 'Отзывов пока нет — будьте первым' ?></span>
        </a>
        <div class="pv__meta">
          <span><?= h(GENDER_LABELS[$p['gender']] ?? $p['gender']) ?></span>
          <?php if ($p['concentration']): ?><span><?= h($p['concentration']) ?></span><?php endif; ?>
          <?php foreach (array_slice($p['accords'], 0, 3) as $a): ?><span><?= h($a) ?></span><?php endforeach; ?>
        </div>

        <div class="buybox" id="buybox" data-id="<?= (int)$p['id'] ?>">
          <div class="buybox__stock <?= $p['in_stock'] ? 'is-in' : 'is-out' ?>"><?= $p['in_stock'] ? 'В наличии' : 'Нет в наличии' ?></div>
          <div class="buybox__vols">
            <?php $first = true; foreach (['5', '10', '15'] as $v): $has = isset($volumes[$v]); ?>
              <button type="button" class="bvol<?= $has && $first && $p['in_stock'] ? ' is-active' : '' ?>" data-vol="<?= $v ?>" data-id="<?= (int)$p['id'] ?>" <?= $has && $p['in_stock'] ? '' : 'disabled' ?>>
                <b><?= $v ?> мл</b><span><?= $has ? format_price($volumes[$v]) : '—' ?></span><?php if ($has && isset(['10' => 1, '15' => 1][$v])): ?><i class="vol__off">−<?= $v === '10' ? 3 : 7 ?>%</i><?php endif; ?>
              </button>
            <?php if ($has) $first = false; endforeach; ?>
          </div>
          <?php if ($p['in_stock'] && $volumes): $v0 = array_key_first($volumes); ?>
            <div class="pv__price"><?= format_price($volumes[$v0]) ?><?php if ($volumes[$v0] < (int)$p['volumes'][$v0]): ?><s><?= format_price((int)$p['volumes'][$v0]) ?></s><?php endif; ?><small>за <?= $v0 ?> мл</small></div>
            <div class="pv__actions">
              <button type="button" class="btn btn--primary" data-add="<?= (int)$p['id'] ?>" data-open-cart="1">В корзину <svg class="icon"><use href="#i-bag"/></svg></button>
              <button type="button" class="icon-btn icon-btn--lg" data-fav="<?= (int)$p['id'] ?>" aria-label="В избранное"><svg class="icon"><use href="#i-heart"/></svg></button>
            </div>
          <?php else: ?>
            <p class="muted">Аромата сейчас нет — забронируйте, и мы сообщим о поступлении.</p>
            <div class="pv__actions">
              <button type="button" class="btn btn--primary" data-book="<?= (int)$p['id'] ?>">Забронировать</button>
              <button type="button" class="icon-btn icon-btn--lg" data-fav="<?= (int)$p['id'] ?>" aria-label="В избранное"><svg class="icon"><use href="#i-heart"/></svg></button>
            </div>
          <?php endif; ?>
        </div>

        <div class="pv__note">
          <div><svg class="icon"><use href="#i-fast"/></svg> Бесплатная доставка по всей России</div>
          <div><svg class="icon"><use href="#i-gift"/></svg> Подарочная упаковка, парфюм 2 мл в подарок от <?= format_price($gift_from) ?></div>
          <div><svg class="icon"><use href="#i-shield"/></svg> <a href="/original/">Оригинальный аромат: декларация и «Честный ЗНАК»</a></div>
        </div>
      </div>
    </div>
  </div>

  <section class="section pd__about" id="about" aria-labelledby="about-title">
    <div class="container">
      <div class="section-head"><div><div class="eyebrow">Характеристики</div><h2 class="h-section" id="about-title">Об аромате</h2></div></div>
      <?php
        $dash = '<span class="muted">—</span>';
        $level = function (int $val, array $names) use ($dash) {
            if (!$val) return $dash;
            $bar = '';
            for ($i = 1; $i <= 5; $i++) $bar .= '<i class="' . ($i <= $val ? 'is-on' : '') . '"></i>';
            return '<span class="spec-level"><span class="profile__bar">' . $bar . '</span>' . h($names[$val]) . '</span>';
        };
        $rows = [
            'Основные аккорды' => $p['accords'] ? '<span class="spec-chips">' . implode('', array_map(fn($a) => '<span>' . h($a) . '</span>', $p['accords'])) . '</span>' : $dash,
            'Верхние ноты' => $p['notes']['top'] !== '' ? h($p['notes']['top']) : $dash,
            'Средние ноты' => $p['notes']['heart'] !== '' ? h($p['notes']['heart']) : $dash,
            'Базовые ноты' => $p['notes']['base'] !== '' ? h($p['notes']['base']) : $dash,
            'Парфюмер' => $p['perfumer'] !== '' ? h($p['perfumer']) : $dash,
            'Страна бренда' => $country !== '' ? h($country) : $dash,
            'Пол' => h(mb_strtolower(GENDER_LABELS[$p['gender']] ?? $p['gender'])),
            'Год выпуска' => $p['year'] ? (string)$p['year'] : $dash,
            'Концентрация' => $p['concentration'] !== '' ? h($p['concentration']) : $dash,
            'Стойкость' => $level($p['longevity'], LONGEVITY),
            'Шлейф' => $level($p['sillage'], SILLAGE),
            'Сезонность' => $p['seasons'] ? h(implode(', ', $p['seasons'])) : $dash,
            'День / вечер' => $p['daytime'] ? h(implode(' / ', $p['daytime'])) : $dash,
        ];
      ?>
      <dl class="specs specs--full">
        <div><dt>Бренд</dt><dd><a href="<?= h(brand_url($p['brand'])) ?>"><?= h($p['brand']) ?></a></dd></div>
        <?php foreach ($rows as $k => $v): ?><div><dt><?= h($k) ?></dt><dd><?= $v ?></dd></div><?php endforeach; ?>
      </dl>
      <div class="about__desc">
        <h3 class="about__h">Описание</h3>
        <?php if ($p['description']): ?>
          <div class="prose"><?= render_text($p['description']) ?></div>
        <?php else: ?>
          <p class="muted">Подробное описание аромата скоро появится. Остались вопросы — <button type="button" class="u-link" data-ask="1">спросите нас</button>.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section section--panel" id="similar" aria-labelledby="similar-title">
    <div class="container">
      <div class="section-head">
        <div><div class="eyebrow">Похожие ароматы</div><h2 class="h-section" id="similar-title">Похожие на <em><?= h($title) ?></em></h2></div>
      </div>
      <?php if ($tabs): ?>
        <div class="sim-tabs" role="tablist">
          <?php $i = 0; foreach ($tabs as $key => [$label, $list]): ?>
            <button type="button" role="tab" class="<?= $i++ ? '' : 'is-active' ?>" data-simtab="<?= $key ?>"><?= h($label) ?> <sup><?= count($list) ?></sup></button>
          <?php endforeach; ?>
        </div>
        <?php $i = 0; foreach ($tabs as $key => [$label, $list]): ?>
          <div class="sim-pane" data-simpane="<?= $key ?>" <?= $i++ ? 'hidden' : '' ?>>
            <div class="sim-pane__nav"><?= rail_nav('sim-' . $key) ?></div>
            <?php rail_block('sim-' . $key, $list); ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <form class="suggest" id="suggestForm" autocomplete="off">
        <div class="suggest__text"><b>Знаете похожий аромат?</b> Подскажите другим покупателям — так формируется подборка «По мнению пользователей».</div>
        <div class="suggest__field">
          <input class="input" name="q" id="suggestInput" placeholder="Начните вводить название" list="suggestList">
          <datalist id="suggestList"></datalist>
          <button class="btn btn--line btn--sm" type="submit">Предложить</button>
        </div>
      </form>
    </div>
  </section>

  <?php if ($brand_more): ?>
  <section class="section" aria-labelledby="brand-more-title">
    <div class="container">
      <div class="section-head">
        <div><div class="eyebrow"><?= h($p['brand']) ?></div><h2 class="h-section" id="brand-more-title">Ещё ароматы <em>бренда</em></h2></div>
        <div class="section-head__side"><a href="<?= h(brand_url($p['brand'])) ?>" class="link-arrow">Все ароматы <?= h($p['brand']) ?> <svg class="icon"><use href="#i-arrow"/></svg></a><?= rail_nav('brandMore') ?></div>
      </div>
      <?php rail_block('brandMore', $brand_more); ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="section section--panel" id="recent" aria-labelledby="recent-title" hidden>
    <div class="container">
      <div class="section-head">
        <div><div class="eyebrow">История</div><h2 class="h-section" id="recent-title">Недавно <em>просмотренные</em></h2></div>
        <?= rail_nav('recentRail') ?>
      </div>
      <div class="rail" id="recentRail"></div>
    </div>
  </section>

  <section class="section" id="reviews" aria-labelledby="reviews-title">
    <div class="container reviews">
      <div class="rating">
        <div class="eyebrow">Отзывы</div>
        <h2 class="h-section" id="reviews-title" style="margin:18px 0 8px">Отзывы о <?= h($title) ?></h2>
        <div class="rating__big"><?= $stats['count'] ? str_replace('.', ',', number_format($stats['avg'], 1)) : '—' ?></div>
        <div class="rating__stars"><?= stars_html((float)$stats['avg']) ?></div>
        <p><?= $stats['count'] ? $stats['count'] . ' ' . plural_ru($stats['count'], 'отзыв', 'отзыва', 'отзывов') : 'Пока нет отзывов' ?></p>
      </div>
      <div class="quotes">
        <?php foreach ($reviews as $rev): ?>
          <figure class="quote">
            <div class="quote__head"><?= stars_html((float)($rev['rating'] ?? 5)) ?><span><?= h(substr((string)($rev['created_at'] ?? ''), 0, 10)) ?></span></div>
            <blockquote class="quote__text"><?= h($rev['text'] ?? '') ?></blockquote>
            <figcaption class="quote__meta"><?= h($rev['author'] ?? '') ?></figcaption>
          </figure>
        <?php endforeach; ?>
        <form class="form quote-form" id="reviewForm" novalidate>
          <h3 class="h-section" style="font-size:32px">Оставить отзыв</h3>
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <div class="field"><label>Оценка</label>
            <div class="rate-input" role="radiogroup" aria-label="Оценка">
              <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="rating" value="<?= $i ?>" id="rate<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>><label for="rate<?= $i ?>" title="<?= $i ?>"><svg class="icon"><use href="#i-star"/></svg></label><?php endfor; ?>
            </div>
          </div>
          <div class="field"><label for="rName">Имя</label><input class="input" id="rName" name="name" required></div>
          <div class="field"><label for="rText">Отзыв</label><textarea class="input" id="rText" name="text" rows="4" required placeholder="Как раскрывается, стойкость, шлейф, кому подойдёт"></textarea></div>
          <label class="consent"><input type="checkbox" name="consent" required><span>Согласен на публикацию отзыва после проверки</span></label>
          <button class="btn btn--primary" type="submit">Отправить</button>
        </form>
      </div>
    </div>
  </section>
</main>

<?php layout_end(['page' => 'product', 'productId' => (int)$p['id']]); ?>
