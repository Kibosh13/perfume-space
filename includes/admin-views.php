<?php
/**
 * Админка: экраны новых разделов. Подключается внутри <main> admin.php. Ожидает $tab, $products, $config.
 */
$checked = fn($cond) => $cond ? 'checked' : '';
$sel = fn($a, $b) => (string)$a === (string)$b ? 'selected' : '';
$hint = fn($t) => '<small style="color:var(--mute);font-size:12px">' . $t . '</small>';
$by_id = [];
foreach ($products as $pp) $by_id[(int)$pp['id']] = $pp;
?>
<datalist id="accordList"><?php foreach (ACCORDS as $a): ?><option value="<?= h($a) ?>"><?php endforeach; ?></datalist>
<datalist id="productList"><?php foreach ($products as $pp): ?><option value="<?= h($pp['name']) ?>"><?php endforeach; ?></datalist>

<?php if ($tab === 'product'):
    $pid = (int)($_GET['id'] ?? 0);
    $p = isset($by_id[$pid]) ? normalize_product($by_id[$pid]) : null;
    if (!$p): ?><div class="admin-card">Аромат не найден. <a href="/admin.php?tab=products">К списку</a></div><?php else: ?>
  <form method="post" enctype="multipart/form-data" class="admin-card form-grid">
    <input type="hidden" name="admin_action" value="save_product_full">
    <input type="hidden" name="product_id" value="<?= $pid ?>">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
      <h2 style="font-family:var(--font-serif);font-size:1.7rem">#<?= $pid ?> · <?= h($p['name']) ?></h2>
      <div style="display:flex;gap:8px"><a class="btn btn--line btn--sm" href="/admin.php?tab=products">← К списку</a><a class="btn btn--line btn--sm" href="<?= h(product_url($p)) ?>" target="_blank">Открыть на сайте</a></div>
    </div>

    <h3 class="adm-h">Основное</h3>
    <div class="two-col-grid">
      <div class="form-group"><label>Бренд</label><input class="form-control" name="brand" value="<?= h($p['brand']) ?>" list="brandList"></div>
      <div class="form-group"><label>Полное название</label><input class="form-control" name="name" value="<?= h($p['name']) ?>"></div>
      <div class="form-group"><label>Короткое название (в карточках)</label><input class="form-control" name="short_name" value="<?= h($p['short_name']) ?>"></div>
      <div class="form-group"><label>Адрес страницы</label><input class="form-control" name="slug" value="<?= h($p['slug']) ?>"><?= $hint('perfume-space.ru/aromat/<b>' . h($p['slug']) . '</b>/ — меняйте осторожно, старые ссылки перестанут работать') ?></div>
      <div class="form-group"><label>Пол</label><select class="form-control" name="gender"><?php foreach (['Унисекс', 'Женские', 'Мужские'] as $g): ?><option value="<?= $g ?>" <?= $sel($p['gender'], $g) ?>><?= GENDER_LABELS[$g] ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Концентрация</label><select class="form-control" name="concentration"><option value="">—</option><?php foreach (CONCENTRATIONS as $c): ?><option <?= $sel($p['concentration'], $c) ?>><?= $c ?></option><?php endforeach; ?></select></div>
    </div>

    <h3 class="adm-h">Цены и наличие</h3>
    <div class="two-col-grid" style="grid-template-columns:repeat(4,1fr)">
      <?php foreach (['5', '10', '15'] as $v): ?><div class="form-group"><label><?= $v ?> мл, ₽</label><input class="form-control" type="number" name="price_<?= $v ?>" value="<?= (int)($p['volumes'][$v] ?? 0) ?>"></div><?php endforeach; ?>
      <div class="form-group"><label>Скидка, %</label><input class="form-control" type="number" name="discount" value="<?= (int)$p['discount'] ?>"></div>
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <label><input type="checkbox" name="in_stock" <?= $checked(!empty($p['in_stock'])) ?>> В наличии</label>
      <label><input type="checkbox" name="is_hit" <?= $checked(!empty($p['is_hit'])) ?>> Хит</label>
      <?php foreach (['new' => 'Новинка', 'niche' => 'Нишевая', 'luxury' => 'Люкс', 'arabic' => 'Арабская', 'vintage' => 'Винтаж'] as $k => $l): ?>
        <label><input type="checkbox" name="tags[]" value="<?= $k ?>" <?= $checked(in_array($k, $p['tags'], true)) ?>> <?= $l ?></label>
      <?php endforeach; ?>
    </div>

    <h3 class="adm-h">Фотографии</h3>
    <div class="adm-images">
      <?php if (!empty($p['image'])): ?><figure><img src="<?= h($p['image']) ?>" alt=""><figcaption>Основное<label><input type="checkbox" name="remove_main" value="1"> удалить</label></figcaption></figure><?php endif; ?>
      <?php foreach ($p['images'] as $img): ?><figure><img src="<?= h($img) ?>" alt=""><figcaption><label><input type="checkbox" name="remove_images[]" value="<?= h($img) ?>"> удалить</label></figcaption></figure><?php endforeach; ?>
    </div>
    <div class="two-col-grid">
      <div class="form-group"><label>Заменить основное фото</label><input type="file" name="image_main" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
      <div class="form-group"><label>Добавить фото для свайпа (можно несколько)</label><input type="file" name="images_extra[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control"></div>
    </div>

    <h3 class="adm-h">Описание аромата</h3>
    <div class="form-group"><label>Основные аккорды (через запятую, по убыванию)</label><input class="form-control" name="accords" value="<?= h(implode(', ', $p['accords'])) ?>" list="accordList"><?= $hint('Из списка: ' . h(implode(', ', ACCORDS)) . '. По аккордам работают фильтр, группы («Цветочные», «Древесные»…) и «Похожие по нотам».') ?></div>
    <div class="two-col-grid" style="grid-template-columns:1fr 1fr 1fr">
      <div class="form-group"><label>Верхние ноты</label><textarea class="form-control" name="notes_top" rows="2"><?= h($p['notes']['top']) ?></textarea></div>
      <div class="form-group"><label>Средние ноты</label><textarea class="form-control" name="notes_heart" rows="2"><?= h($p['notes']['heart']) ?></textarea></div>
      <div class="form-group"><label>Базовые ноты</label><textarea class="form-control" name="notes_base" rows="2"><?= h($p['notes']['base']) ?></textarea></div>
    </div>
    <div class="two-col-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="form-group"><label>Парфюмер</label><input class="form-control" name="perfumer" value="<?= h($p['perfumer']) ?>"></div>
      <div class="form-group"><label>Страна бренда</label><input class="form-control" name="country" value="<?= h($p['country']) ?>" placeholder="<?= h(brand_info($p['brand'])['country'] ?: 'берётся из бренда') ?>"></div>
      <div class="form-group"><label>Год выпуска</label><input class="form-control" type="number" name="year" value="<?= $p['year'] ?: '' ?>"></div>
      <div class="form-group"><label>Стойкость</label><select class="form-control" name="longevity"><option value="0">—</option><?php foreach (LONGEVITY as $k => $l): ?><option value="<?= $k ?>" <?= $sel($p['longevity'], $k) ?>><?= $k ?> — <?= $l ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Шлейф</label><select class="form-control" name="sillage"><option value="0">—</option><?php foreach (SILLAGE as $k => $l): ?><option value="<?= $k ?>" <?= $sel($p['sillage'], $k) ?>><?= $k ?> — <?= $l ?></option><?php endforeach; ?></select><?= $hint('4–5 — аромат попадёт в «Шлейфовую парфюмерию»') ?></div>
      <div class="form-group"><label>Сезон и время</label><div style="display:flex;gap:12px;flex-wrap:wrap;padding-top:8px">
        <?php foreach (SEASONS as $s): ?><label><input type="checkbox" name="seasons[]" value="<?= $s ?>" <?= $checked(in_array($s, $p['seasons'], true)) ?>> <?= $s ?></label><?php endforeach; ?>
        <?php foreach (DAYTIME as $s): ?><label><input type="checkbox" name="daytime[]" value="<?= $s ?>" <?= $checked(in_array($s, $p['daytime'], true)) ?>> <?= $s ?></label><?php endforeach; ?>
      </div></div>
    </div>
    <div class="form-group"><label>Текстовое описание</label><textarea class="form-control" name="description" rows="6"><?= h($p['description']) ?></textarea><?= $hint('Абзацы — пустой строкой, подзаголовок — «## », список — «- », жирный — **текст**') ?></div>

    <h3 class="adm-h">Похожие ароматы «По мнению пользователей»</h3>
    <div class="form-group"><label>Список (id или названия, каждый с новой строки)</label>
      <textarea class="form-control" name="similar" rows="4" placeholder="Например: Tom Ford Tobacco Vanille"><?= h(implode("\n", array_map(fn($id) => $by_id[$id]['name'] ?? $id, $p['similar_ids']))) ?></textarea>
      <?php $votes = similar_votes()[(string)$pid] ?? []; arsort($votes); if ($votes): ?><?= $hint('Голоса покупателей: ' . h(implode(', ', array_map(fn($id, $n) => ($by_id[(int)$id]['name'] ?? $id) . " ({$n})", array_keys($votes), $votes)))) ?><?php endif; ?>
    </div>

    <h3 class="adm-h">SEO</h3>
    <div class="two-col-grid">
      <div class="form-group"><label>Title</label><input class="form-control" name="meta_title" value="<?= h($p['meta_title']) ?>" placeholder="Заполняется автоматически"></div>
      <div class="form-group"><label>Description</label><input class="form-control" name="meta_description" value="<?= h($p['meta_description']) ?>" placeholder="Заполняется автоматически"></div>
    </div>
    <datalist id="brandList"><?php foreach (array_keys(brand_list()) as $b): ?><option value="<?= h($b) ?>"><?php endforeach; ?></datalist>
    <button class="btn btn--primary" type="submit">Сохранить карточку</button>
  </form>
<?php endif; ?>

<?php elseif ($tab === 'brands'):
    $edit = $_GET['brand'] ?? '';
    if ($edit !== '' && isset(brand_list()[$edit])): $b = brand_info($edit); ?>
  <form method="post" enctype="multipart/form-data" class="admin-card form-grid">
    <input type="hidden" name="admin_action" value="save_brand">
    <input type="hidden" name="brand_name" value="<?= h($edit) ?>">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
      <h2 style="font-family:var(--font-serif);font-size:1.7rem"><?= h($edit) ?> · <?= brand_list()[$edit] ?> ароматов</h2>
      <div style="display:flex;gap:8px"><a class="btn btn--line btn--sm" href="/admin.php?tab=brands">← Все бренды</a><a class="btn btn--line btn--sm" href="<?= h(brand_url($edit)) ?>" target="_blank">Открыть на сайте</a></div>
    </div>
    <div class="two-col-grid">
      <div class="form-group"><label>Адрес страницы</label><input class="form-control" name="slug" value="<?= h($b['slug']) ?>"><?= $hint('/brand/' . h($b['slug']) . '/') ?></div>
      <div class="form-group"><label>Страна</label><input class="form-control" name="country" value="<?= h($b['country']) ?>"></div>
      <div class="form-group"><label>Сегмент (для категорий)</label><select class="form-control" name="segment"><option value="">—</option><?php foreach (SEGMENTS as $k => $l): ?><option value="<?= $k ?>" <?= $sel($b['segment'], $k) ?>><?= $l ?></option><?php endforeach; ?></select><?= $hint('Все ароматы бренда попадут в «Нишевую», «Люкс» или «Арабскую» парфюмерию') ?></div>
      <div class="form-group"><label>Варианты написания для поиска</label><input class="form-control" name="aliases" value="<?= h($b['aliases']) ?>" placeholder="через запятую: шанель, шанэль"></div>
    </div>
    <div class="form-group"><label>Логотип / изображение</label>
      <?php if ($b['logo']): ?><img src="<?= h($b['logo']) ?>" alt="" style="width:120px;height:120px;object-fit:contain;background:#0e1422;border-radius:6px;margin-bottom:6px"><label><input type="checkbox" name="remove_logo" value="1"> удалить</label><?php endif; ?>
      <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="form-control">
    </div>
    <div class="form-group"><label>Краткое описание (под заголовком)</label><textarea class="form-control" name="description" rows="3"><?= h($b['description']) ?></textarea><?= $hint('Пусто — подставится автоматический текст') ?></div>
    <div class="form-group"><label>SEO-текст (внизу страницы)</label><textarea class="form-control" name="seo_text" rows="8"><?= h($b['seo_text']) ?></textarea><?= $hint('«## » — подзаголовок, «- » — список. Пусто — шаблонный текст') ?></div>
    <div class="two-col-grid">
      <div class="form-group"><label>Title</label><input class="form-control" name="meta_title" value="<?= h($b['meta_title']) ?>"></div>
      <div class="form-group"><label>Description</label><input class="form-control" name="meta_description" value="<?= h($b['meta_description']) ?>"></div>
    </div>
    <button class="btn btn--primary" type="submit">Сохранить бренд</button>
  </form>
    <?php else: ?>
  <div class="admin-card">
    <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:14px">Страницы брендов (<?= count(brand_list()) ?>)</h2>
    <div style="overflow-x:auto"><table class="admin-table">
      <thead><tr><th>Бренд</th><th>Ароматов</th><th>Страна</th><th>Сегмент</th><th>Описание</th><th>Логотип</th><th></th></tr></thead>
      <tbody><?php foreach (brand_list() as $name => $n): $b = brand_info($name); ?>
        <tr><td><b><?= h($name) ?></b></td><td><?= $n ?></td><td><?= h($b['country']) ?></td><td><?= h(SEGMENTS[$b['segment']] ?? '') ?></td><td><?= $b['description'] ? '✓' : '<span class="muted">авто</span>' ?></td><td><?= $b['logo'] ? '✓' : '—' ?></td>
        <td><a class="btn btn--line btn--sm" href="/admin.php?tab=brands&brand=<?= urlencode($name) ?>">Редактировать</a></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
    <?php endif; ?>

<?php elseif ($tab === 'collections'):
    $all = collections(false);
    $edit = $_GET['slug'] ?? null;
    $c = null;
    foreach ($all as $x) if ($edit !== null && $x['slug'] === $edit) $c = $x;
    if ($edit !== null):
        $c = $c ?? ['slug' => '', 'title' => '', 'h1' => '', 'menu' => ['Подборки'], 'rules' => [], 'ids' => [], 'sort' => 100, 'active' => true, 'home_block' => false, 'intro' => '', 'seo_text' => '', 'meta_title' => '', 'meta_description' => '', 'image' => '', 'default_sort' => ''];
        $r = (array)($c['rules'] ?? []); ?>
  <form method="post" enctype="multipart/form-data" class="admin-card form-grid">
    <input type="hidden" name="admin_action" value="save_collection">
    <input type="hidden" name="orig_slug" value="<?= h($c['slug']) ?>">
    <input type="hidden" name="image_current" value="<?= h($c['image'] ?? '') ?>">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
      <h2 style="font-family:var(--font-serif);font-size:1.7rem"><?= $c['slug'] ? h($c['title']) . ' · ' . count(collection_ids($c)) . ' ароматов' : 'Новая подборка / SEO-страница' ?></h2>
      <div style="display:flex;gap:8px"><a class="btn btn--line btn--sm" href="/admin.php?tab=collections">← Все подборки</a><?php if ($c['slug']): ?><a class="btn btn--line btn--sm" href="<?= h(collection_url($c)) ?>" target="_blank">Открыть на сайте</a><?php endif; ?></div>
    </div>
    <div class="two-col-grid">
      <div class="form-group"><label>Название (в меню и хлебных крошках)</label><input class="form-control" name="title" value="<?= h($c['title']) ?>" required></div>
      <div class="form-group"><label>Заголовок H1</label><input class="form-control" name="h1" value="<?= h($c['h1'] ?? '') ?>" placeholder="Если пусто — как название"></div>
      <div class="form-group"><label>Адрес страницы</label><input class="form-control" name="slug" value="<?= h($c['slug']) ?>" placeholder="латиницей, например new-year"><?= $hint('/category/<b>' . h($c['slug'] ?: '…') . '</b>/') ?></div>
      <div class="form-group"><label>Порядок</label><input class="form-control" type="number" name="sort" value="<?= (int)($c['sort'] ?? 100) ?>"></div>
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <label><input type="checkbox" name="active" <?= $checked(!empty($c['active'])) ?>> Опубликована</label>
      <label><input type="checkbox" name="home_block" <?= $checked(!empty($c['home_block'])) ?>> Показывать блоком на главной (перед «Новинками»)</label>
    </div>
    <div class="form-group"><label>Раздел меню «Каталог»</label><div style="display:flex;gap:18px;flex-wrap:wrap;padding-top:6px">
      <?php foreach (MENU_GROUPS as $g): ?><label><input type="checkbox" name="menu[]" value="<?= $g ?>" <?= $checked(in_array($g, (array)$c['menu'], true)) ?>> <?= $g ?></label><?php endforeach; ?>
    </div></div>

    <h3 class="adm-h">Какие ароматы попадают в подборку</h3>
    <p style="color:var(--text-2);font-size:14px">Можно задать правила (ароматы подбираются автоматически), выбрать ароматы вручную — или и то и другое.</p>
    <div class="two-col-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="form-group"><label>Пол</label><div style="display:flex;gap:12px;padding-top:8px"><?php foreach (GENDER_LABELS as $g => $l): ?><label><input type="checkbox" name="r_gender[]" value="<?= $g ?>" <?= $checked(in_array($g, (array)($r['gender'] ?? []), true)) ?>> <?= $l ?></label><?php endforeach; ?></div></div>
      <div class="form-group"><label>Концентрация</label><select class="form-control" name="r_concentration"><option value="">любая</option><?php foreach (CONCENTRATIONS as $x): ?><option <?= $sel($r['concentration'] ?? '', $x) ?>><?= $x ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Аккорд</label><input class="form-control" name="r_accord" value="<?= h($r['accord'] ?? '') ?>" list="accordList"></div>
      <div class="form-group"><label>Сезон</label><select class="form-control" name="r_season"><option value="">любой</option><?php foreach (SEASONS as $x): ?><option <?= $sel($r['season'] ?? '', $x) ?>><?= $x ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Сегмент</label><select class="form-control" name="r_segment"><option value="">любой</option><?php foreach (SEGMENTS as $k => $l): ?><option value="<?= $k ?>" <?= $sel($r['segment'] ?? '', $k) ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Особое</label><select class="form-control" name="r_flag"><option value="">—</option><?php foreach (['hit' => 'Хиты продаж', 'new' => 'Новинки', 'sale' => 'Со скидкой', 'sillage' => 'Шлейфовые (шлейф 4–5)'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel($r['flag'] ?? '', $k) ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Бренды (через запятую)</label><input class="form-control" name="r_brand" value="<?= h(implode(', ', (array)($r['brand'] ?? []))) ?>"></div>
      <div class="form-group"><label>Цена 5 мл от / до, ₽</label><div style="display:flex;gap:8px"><input class="form-control" type="number" name="r_price_min" value="<?= (int)($r['price_min'] ?? 0) ?: '' ?>"><input class="form-control" type="number" name="r_price_max" value="<?= (int)($r['price_max'] ?? 0) ?: '' ?>"></div></div>
      <div class="form-group"><label>&nbsp;</label><label><input type="checkbox" name="r_in_stock" <?= $checked(!empty($r['in_stock'])) ?>> Только в наличии</label></div>
    </div>
    <div class="form-group"><label>Ароматы вручную (id или названия, каждый с новой строки)</label>
      <textarea class="form-control" name="ids" rows="6" placeholder="Tom Ford Tobacco Vanille&#10;846"><?= h(implode("\n", array_map(fn($id) => $by_id[(int)$id]['name'] ?? $id, (array)($c['ids'] ?? [])))) ?></textarea>
      <?= $hint('Подсказка: начните вводить название в поле ниже и скопируйте. Порядок сохраняется.') ?>
      <input class="form-control" list="productList" placeholder="Поиск названия…" style="margin-top:6px" onchange="const t=this.form.ids;t.value=(t.value.trim()?t.value.trim()+'\n':'')+this.value;this.value=''">
    </div>
    <div class="form-group"><label>Сортировка по умолчанию</label><select class="form-control" name="default_sort"><option value="">Популярные</option><?php foreach (['new' => 'Новинки', 'price_asc' => 'Цена ↑', 'price_desc' => 'Цена ↓', 'rating' => 'Рейтинг'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel($c['default_sort'] ?? '', $k) ?>><?= $l ?></option><?php endforeach; ?></select></div>

    <h3 class="adm-h">Тексты и SEO</h3>
    <div class="form-group"><label>Вступление (под заголовком)</label><textarea class="form-control" name="intro" rows="2"><?= h($c['intro'] ?? '') ?></textarea></div>
    <div class="form-group"><label>SEO-текст (внизу страницы)</label><textarea class="form-control" name="seo_text" rows="8"><?= h($c['seo_text'] ?? '') ?></textarea><?= $hint('«## » — подзаголовок, «- » — список. Пусто — шаблонный текст') ?></div>
    <div class="two-col-grid">
      <div class="form-group"><label>Title</label><input class="form-control" name="meta_title" value="<?= h($c['meta_title'] ?? '') ?>"></div>
      <div class="form-group"><label>Description</label><input class="form-control" name="meta_description" value="<?= h($c['meta_description'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Обложка (необязательно)</label><?php if (!empty($c['image'])): ?><img src="<?= h($c['image']) ?>" alt="" style="max-width:320px;border-radius:6px"><label><input type="checkbox" name="remove_image" value="1"> удалить</label><?php endif; ?><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
    <button class="btn btn--primary" type="submit">Сохранить подборку</button>
  </form>
  <?php if ($c['slug']): ?>
  <form method="post" onsubmit="return confirm('Удалить подборку?')" style="margin-top:-8px"><input type="hidden" name="admin_action" value="delete_collection"><input type="hidden" name="slug" value="<?= h($c['slug']) ?>"><button class="btn btn--ghost btn--sm">Удалить подборку</button></form>
  <?php endif; ?>
    <?php else: ?>
  <div class="admin-card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
      <h2 style="font-family:var(--font-serif);font-size:1.6rem">Категории, подборки и SEO-страницы (<?= count($all) ?>)</h2>
      <a class="btn btn--primary btn--sm" href="/admin.php?tab=collections&slug=">+ Создать подборку</a>
    </div>
    <div style="overflow-x:auto"><table class="admin-table">
      <thead><tr><th>Название</th><th>Адрес</th><th>Меню</th><th>Ароматов</th><th>Статус</th><th></th></tr></thead>
      <tbody><?php foreach ($all as $x): ?>
        <tr><td><b><?= h($x['title']) ?></b><?= !empty($x['home_block']) ? ' <span class="p-badge p-badge--hit">на главной</span>' : '' ?></td><td>/category/<?= h($x['slug']) ?>/</td><td><?= h(implode(', ', (array)($x['menu'] ?? []))) ?></td><td><?= count(collection_ids($x)) ?></td><td><?= !empty($x['active']) ? 'опубликована' : '<span class="muted">скрыта</span>' ?></td>
        <td><a class="btn btn--line btn--sm" href="/admin.php?tab=collections&slug=<?= urlencode($x['slug']) ?>">Редактировать</a></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
    <?php endif; ?>

<?php elseif ($tab === 'promos'):
    $codes = $config['promo_codes'] ?? [];
    $editc = mb_strtoupper((string)($_GET['code'] ?? ''));
    $pc = ['code' => '', 'title' => '', 'type' => 'percent', 'value' => 10, 'min_sum' => 0, 'expires' => '', 'max_uses' => 0, 'once' => true, 'first_order' => false, 'active' => true, 'show_on_home' => false, 'lead' => '', 'text' => '', 'used' => 0];
    foreach ($codes as $x) if (mb_strtoupper((string)$x['code']) === $editc && $editc !== '') { $pc = array_merge($pc, $x); [$pc['type'], $pc['value']] = promo_value($x); }
    ?>
  <div class="admin-card">
    <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:14px">Промокоды (<?= count($codes) ?>)</h2>
    <div style="overflow-x:auto"><table class="admin-table">
      <thead><tr><th>Код</th><th>Скидка</th><th>Условия</th><th>Использован</th><th>Статус</th><th></th></tr></thead>
      <tbody><?php foreach ($codes as $x): [$t, $v] = promo_value($x); ?>
        <tr><td><b><?= h($x['code']) ?></b><div class="muted" style="font-size:12px"><?= h($x['title'] ?? '') ?></div></td>
        <td><?= $t === 'amount' ? format_price($v) : $v . '%' ?></td>
        <td style="font-size:12px"><?= implode('<br>', array_filter([!empty($x['min_sum']) ? 'от ' . format_price((int)$x['min_sum']) : '', !empty($x['expires']) ? 'до ' . h($x['expires']) : '', !empty($x['max_uses']) ? 'лимит ' . (int)$x['max_uses'] : '', !empty($x['once']) ? 'один раз на покупателя' : '', !empty($x['first_order']) ? 'первый заказ' : '', !empty($x['show_on_home']) ? 'баннер на главной' : ''])) ?></td>
        <td><?= (int)($x['used'] ?? 0) ?></td><td><?= !empty($x['active']) ? 'активен' : '<span class="muted">выключен</span>' ?></td>
        <td style="white-space:nowrap"><a class="btn btn--line btn--sm" href="/admin.php?tab=promos&code=<?= urlencode($x['code']) ?>">Изменить</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Удалить промокод?')"><input type="hidden" name="admin_action" value="delete_promo"><input type="hidden" name="code" value="<?= h($x['code']) ?>"><button class="btn btn--ghost btn--sm">Удалить</button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
  <form method="post" class="admin-card form-grid">
    <input type="hidden" name="admin_action" value="save_promo">
    <input type="hidden" name="orig_code" value="<?= h($pc['code']) ?>">
    <h2 style="font-family:var(--font-serif);font-size:1.45rem"><?= $pc['code'] ? 'Изменить ' . h($pc['code']) : '+ Новый промокод' ?></h2>
    <div class="two-col-grid" style="grid-template-columns:repeat(4,1fr)">
      <div class="form-group"><label>Код</label><input class="form-control" name="code" value="<?= h($pc['code']) ?>" required placeholder="NEWYEAR"></div>
      <div class="form-group"><label>Тип скидки</label><select class="form-control" name="type"><option value="percent" <?= $sel($pc['type'], 'percent') ?>>Процент</option><option value="amount" <?= $sel($pc['type'], 'amount') ?>>Сумма, ₽</option></select></div>
      <div class="form-group"><label>Размер</label><input class="form-control" type="number" name="value" value="<?= (int)$pc['value'] ?>"></div>
      <div class="form-group"><label>Название</label><input class="form-control" name="title" value="<?= h($pc['title']) ?>" placeholder="Скидка 20%"></div>
      <div class="form-group"><label>Мин. сумма заказа, ₽</label><input class="form-control" type="number" name="min_sum" value="<?= (int)$pc['min_sum'] ?: '' ?>"></div>
      <div class="form-group"><label>Действует до</label><input class="form-control" type="date" name="expires" value="<?= h($pc['expires']) ?>"></div>
      <div class="form-group"><label>Лимит использований</label><input class="form-control" type="number" name="max_uses" value="<?= (int)$pc['max_uses'] ?: '' ?>" placeholder="без лимита"></div>
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <label><input type="checkbox" name="active" <?= $checked(!empty($pc['active'])) ?>> Активен</label>
      <label><input type="checkbox" name="once" <?= $checked(!empty($pc['once'])) ?>> Один раз на покупателя (по телефону/почте)</label>
      <label><input type="checkbox" name="first_order" <?= $checked(!empty($pc['first_order'])) ?>> Только на первый заказ</label>
      <label><input type="checkbox" name="show_on_home" <?= $checked(!empty($pc['show_on_home'])) ?>> Баннер на первом экране</label>
    </div>
    <div class="two-col-grid">
      <div class="form-group"><label>Заголовок баннера</label><input class="form-control" name="lead" value="<?= h($pc['lead']) ?>"></div>
      <div class="form-group"><label>Текст баннера</label><input class="form-control" name="text" value="<?= h($pc['text']) ?>"></div>
    </div>
    <button class="btn btn--primary" type="submit">Сохранить промокод</button>
  </form>

<?php elseif ($tab === 'pages'):
    $pages = info_pages();
    $edit = $_GET['slug'] ?? null;
    $pg = null;
    foreach ($pages as $x) if ($edit !== null && $x['slug'] === $edit) $pg = $x;
    if ($edit !== null):
        $pg = $pg ?? ['slug' => '', 'title' => '', 'group' => 'Покупателям', 'sort' => 100, 'lead' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'documents' => []]; ?>
  <form method="post" enctype="multipart/form-data" class="admin-card form-grid">
    <input type="hidden" name="admin_action" value="save_page">
    <input type="hidden" name="orig_slug" value="<?= h($pg['slug']) ?>">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
      <h2 style="font-family:var(--font-serif);font-size:1.7rem"><?= $pg['slug'] ? h($pg['title']) : 'Новая страница' ?></h2>
      <div style="display:flex;gap:8px"><a class="btn btn--line btn--sm" href="/admin.php?tab=pages">← Все страницы</a><?php if ($pg['slug']): ?><a class="btn btn--line btn--sm" href="/<?= h($pg['slug']) ?>/" target="_blank">Открыть на сайте</a><?php endif; ?></div>
    </div>
    <div class="two-col-grid" style="grid-template-columns:2fr 1fr 1fr 1fr">
      <div class="form-group"><label>Заголовок</label><input class="form-control" name="title" value="<?= h($pg['title']) ?>" required></div>
      <div class="form-group"><label>Адрес</label><input class="form-control" name="slug" value="<?= h($pg['slug']) ?>"><?= $hint('/' . h($pg['slug'] ?: '…') . '/') ?></div>
      <div class="form-group"><label>Раздел подвала</label><select class="form-control" name="group"><?php foreach (['Покупателям', 'Документы', 'Скрытая'] as $g): ?><option <?= $sel($pg['group'] ?? '', $g) ?>><?= $g ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Порядок</label><input class="form-control" type="number" name="sort" value="<?= (int)($pg['sort'] ?? 100) ?>"></div>
    </div>
    <div class="form-group"><label>Вступление</label><textarea class="form-control" name="lead" rows="2"><?= h($pg['lead'] ?? '') ?></textarea></div>
    <div class="form-group"><label>Текст страницы</label><textarea class="form-control" name="body" rows="22" style="font-family:ui-monospace,Menlo,monospace;font-size:13px"><?= h($pg['body'] ?? '') ?></textarea>
      <?= $hint('«## » — подзаголовок, «- » — пункт списка, **жирный**, [текст](/ссылка/), [заполнить: …] — подсветится жёлтым. Подстановки из настроек: {entity} {inn} {ogrnip} {address} {email} {phone} {declaration} {work_hours} {gift_from} {bundle_min} {bundle_pct}') ?></div>
    <h3 class="adm-h">Документы (сертификаты, декларации — картинки или PDF)</h3>
    <div class="adm-images">
      <?php foreach ((array)($pg['documents'] ?? []) as $i => $d): ?>
        <figure><?php if (str_ends_with(strtolower($d['file']), '.pdf')): ?><a href="<?= h($d['file']) ?>" target="_blank" style="display:flex;height:120px;align-items:center;justify-content:center">PDF</a><?php else: ?><img src="<?= h($d['file']) ?>" alt=""><?php endif; ?>
          <figcaption><input class="admin-input-sm" name="doc_title[<?= $i ?>]" value="<?= h($d['title'] ?? '') ?>"><label><input type="checkbox" name="remove_doc[<?= $i ?>]" value="1"> удалить</label></figcaption></figure>
      <?php endforeach; ?>
    </div>
    <div class="two-col-grid">
      <div class="form-group"><label>Добавить файлы</label><input type="file" name="docs_new[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="form-control"></div>
      <div class="form-group"><label>Подпись к новым файлам</label><input class="form-control" name="doc_new_title" placeholder="Декларация соответствия ЕАЭС"></div>
    </div>
    <div class="two-col-grid">
      <div class="form-group"><label>Title</label><input class="form-control" name="meta_title" value="<?= h($pg['meta_title'] ?? '') ?>"></div>
      <div class="form-group"><label>Description</label><input class="form-control" name="meta_description" value="<?= h($pg['meta_description'] ?? '') ?>"></div>
    </div>
    <button class="btn btn--primary" type="submit">Сохранить страницу</button>
  </form>
  <?php if ($pg['slug']): ?>
  <form method="post" onsubmit="return confirm('Удалить страницу?')"><input type="hidden" name="admin_action" value="delete_page"><input type="hidden" name="slug" value="<?= h($pg['slug']) ?>"><button class="btn btn--ghost btn--sm">Удалить страницу</button></form>
  <?php endif; ?>
    <?php else: ?>
  <div class="admin-card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
      <h2 style="font-family:var(--font-serif);font-size:1.6rem">Информационные и юридические страницы</h2>
      <a class="btn btn--primary btn--sm" href="/admin.php?tab=pages&slug=">+ Создать страницу</a>
    </div>
    <table class="admin-table"><thead><tr><th>Страница</th><th>Адрес</th><th>Раздел</th><th>Незаполненные поля</th><th></th></tr></thead><tbody>
      <?php foreach ($pages as $x): $tbd = substr_count((string)($x['body'] ?? ''), '[заполнить'); ?>
        <tr><td><b><?= h($x['title']) ?></b></td><td>/<?= h($x['slug']) ?>/</td><td><?= h($x['group'] ?? '') ?></td><td><?= $tbd ? '<span class="tbd">' . $tbd . '</span>' : '—' ?></td><td><a class="btn btn--line btn--sm" href="/admin.php?tab=pages&slug=<?= urlencode($x['slug']) ?>">Редактировать</a></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
    <?php endif; ?>
<?php endif; ?>
