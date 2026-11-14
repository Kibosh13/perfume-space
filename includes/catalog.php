<?php
/**
 * Perfume Space — каталог: бренды, подборки, карточки, похожие ароматы, рейтинги
 */
require_once __DIR__ . '/functions.php';

const ACCORDS = ['Цветочный', 'Древесный', 'Цитрусовый', 'Мускусный', 'Свежий', 'Фруктовый', 'Фужерный', 'Восточный', 'Зелёный', 'Кожаный', 'Удовый', 'Пряный', 'Шипровый', 'Амбровый', 'Сладкий', 'Гурманский', 'Пудровый', 'Ванильный', 'Табачный', 'Морской'];
const SEASONS = ['Зима', 'Весна', 'Лето', 'Осень'];
const DAYTIME = ['День', 'Вечер'];
const CONCENTRATIONS = ['Туалетная вода', 'Парфюмерная вода', 'Духи', 'Одеколон'];
const SEGMENTS = ['niche' => 'Селективная / нишевая', 'luxury' => 'Люкс / элитная', 'arabic' => 'Восточная / арабская', 'vintage' => 'Винтажная'];
const GENDER_LABELS = ['Женские' => 'Женский', 'Мужские' => 'Мужской', 'Унисекс' => 'Унисекс'];
const LONGEVITY = [1 => 'Очень слабая', 2 => 'Слабая', 3 => 'Средняя', 4 => 'Стойкая', 5 => 'Очень стойкая'];
const SILLAGE = [1 => 'Едва заметный', 2 => 'Лёгкий', 3 => 'Умеренный', 4 => 'Заметный', 5 => 'Мощный'];

/* ---------- Products ---------- */

function catalog(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (get_products() as $p) {
            $cache[(int)$p['id']] = normalize_product($p);
        }
    }
    return $cache;
}

function normalize_product(array $p): array {
    $p['images'] = array_values(array_filter((array)($p['images'] ?? []), 'is_string'));
    $p['accords'] = array_values(array_filter((array)($p['accords'] ?? [])));
    $p['seasons'] = array_values(array_filter((array)($p['seasons'] ?? [])));
    $p['daytime'] = array_values(array_filter((array)($p['daytime'] ?? [])));
    $p['tags'] = array_values(array_filter((array)($p['tags'] ?? [])));
    $p['similar_ids'] = array_values(array_map('intval', (array)($p['similar_ids'] ?? [])));
    $p['notes'] = array_merge(['top' => '', 'heart' => '', 'base' => ''], (array)($p['notes'] ?? []));
    foreach (['perfumer', 'country', 'concentration', 'description', 'meta_title', 'meta_description'] as $k) {
        $p[$k] = trim((string)($p[$k] ?? ''));
    }
    $p['year'] = (int)($p['year'] ?? 0);
    $p['longevity'] = max(0, min(5, (int)($p['longevity'] ?? 0)));
    $p['sillage'] = max(0, min(5, (int)($p['sillage'] ?? 0)));
    $p['slug'] = $p['slug'] ?: slugify($p['name']) . '-' . $p['id'];
    return $p;
}

function product_url(array $p): string {
    return '/aromat/' . rawurlencode($p['slug']) . '/';
}

function product_by_slug(string $slug): ?array {
    foreach (catalog() as $p) {
        if ($p['slug'] === $slug) return $p;
    }
    return null;
}

function product_title(array $p): string {
    return $p['short_name'] ?: $p['name'];
}

/** Все изображения аромата: основное + дополнительные */
function product_images(array $p): array {
    $list = array_merge([$p['image'] ?? ''], $p['images']);
    return array_values(array_unique(array_filter($list)));
}

function product_min_price(array $p): int {
    $prices = [];
    foreach (array_keys($p['volumes'] ?? []) as $v) {
        $prices[] = product_price($p, (string)$v);
    }
    return $prices ? min($prices) : 0;
}

/** Ноты аромата одним списком в нижнем регистре */
function product_notes(array $p): array {
    $all = [];
    foreach (['top', 'heart', 'base'] as $k) {
        foreach (preg_split('/\s*[,;]\s*/u', (string)$p['notes'][$k]) as $n) {
            $n = mb_strtolower(trim($n), 'UTF-8');
            if ($n !== '') $all[] = $n;
        }
    }
    return array_values(array_unique($all));
}

function product_segments(array $p): array {
    $brand = brand_info($p['brand']);
    $seg = $p['tags'];
    if (!empty($brand['segment'])) $seg[] = $brand['segment'];
    return array_values(array_unique($seg));
}

function product_country(array $p): string {
    return $p['country'] !== '' ? $p['country'] : (string)(brand_info($p['brand'])['country'] ?? '');
}

/* ---------- Reviews, sales ---------- */

function review_stats(): array {
    static $stats = null;
    if ($stats !== null) return $stats;
    $stats = [];
    foreach (get_reviews() as $r) {
        $pid = (int)($r['product_id'] ?? 0);
        if (!$pid || ($r['status'] ?? '') !== 'published') continue;
        $stats[$pid]['sum'] = ($stats[$pid]['sum'] ?? 0) + (int)($r['rating'] ?? 5);
        $stats[$pid]['count'] = ($stats[$pid]['count'] ?? 0) + 1;
    }
    foreach ($stats as &$s) {
        $s['avg'] = round($s['sum'] / $s['count'], 1);
    }
    return $stats;
}

function product_reviews(int $pid): array {
    $list = array_filter(get_reviews(), fn($r) => (int)($r['product_id'] ?? 0) === $pid && ($r['status'] ?? '') === 'published');
    return array_reverse(array_values($list));
}

/** Сколько раз аромат покупали (по заказам) */
function sales_counts(): array {
    static $sales = null;
    if ($sales !== null) return $sales;
    $sales = [];
    foreach (get_orders() as $o) {
        if (($o['type'] ?? 'order') === 'request') continue;
        foreach ($o['items'] ?? [] as $it) {
            $id = (int)($it['id'] ?? 0);
            $sales[$id] = ($sales[$id] ?? 0) + (int)($it['qty'] ?? 1);
        }
    }
    return $sales;
}

function popularity(array $p): int {
    return (sales_counts()[(int)$p['id']] ?? 0) * 10 + (!empty($p['is_hit']) ? 25 : 0) + (review_stats()[(int)$p['id']]['count'] ?? 0) * 3;
}

/* ---------- Brands ---------- */

function brands_data(): array {
    static $data = null;
    if ($data === null) {
        $data = json_load(BRANDS_FILE, []);
    }
    return $data;
}

/** Бренды каталога с количеством ароматов (по названию) */
function brand_list(): array {
    static $list = null;
    if ($list !== null) return $list;
    $list = [];
    foreach (catalog() as $p) {
        $list[$p['brand']] = ($list[$p['brand']] ?? 0) + 1;
    }
    ksort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

function brand_info(string $name): array {
    $data = brands_data();
    $info = $data[$name] ?? [];
    return array_merge([
        'name' => $name,
        'slug' => slugify($name),
        'description' => '',
        'seo_text' => '',
        'logo' => '',
        'country' => '',
        'segment' => '',
        'aliases' => '',
        'meta_title' => '',
        'meta_description' => '',
    ], array_filter($info, fn($v) => $v !== '' && $v !== null));
}

function brand_url(string $name): string {
    return '/brand/' . rawurlencode(brand_info($name)['slug']) . '/';
}

function brand_by_slug(string $slug): ?array {
    foreach (array_keys(brand_list()) as $name) {
        $b = brand_info($name);
        if ($b['slug'] === $slug) return $b;
    }
    return null;
}

/* ---------- Collections (категории и SEO-подборки) ---------- */

function collections(bool $only_active = true): array {
    $list = json_load(COLLECTIONS_FILE, []);
    if ($only_active) {
        $list = array_values(array_filter($list, fn($c) => !empty($c['active'])));
    }
    usort($list, fn($a, $b) => ((int)($a['sort'] ?? 100)) <=> ((int)($b['sort'] ?? 100)));
    return $list;
}

function collection_by_slug(string $slug): ?array {
    foreach (collections() as $c) {
        if (($c['slug'] ?? '') === $slug) return $c;
    }
    return null;
}

function collection_url(array $c): string {
    return '/category/' . rawurlencode($c['slug']) . '/';
}

const MENU_GROUPS = ['Парфюмерия', 'Категории', 'Концентрация', 'Группы', 'Подборки'];

function collections_menu(): array {
    $menu = array_fill_keys(MENU_GROUPS, []);
    foreach (collections() as $c) {
        foreach ((array)($c['menu'] ?? []) as $g) {
            if (isset($menu[$g])) $menu[$g][] = $c;
        }
    }
    return array_filter($menu);
}

function product_matches_rules(array $p, array $r): bool {
    if (!empty($r['gender']) && !in_array($p['gender'], (array)$r['gender'], true)) return false;
    if (!empty($r['brand']) && !in_array($p['brand'], (array)$r['brand'], true)) return false;
    if (!empty($r['concentration']) && $p['concentration'] !== $r['concentration']) return false;
    if (!empty($r['accord']) && !in_array(mb_strtolower($r['accord']), array_map('mb_strtolower', $p['accords']), true)) return false;
    if (!empty($r['season']) && !in_array($r['season'], $p['seasons'], true)) return false;
    if (!empty($r['segment']) && !in_array($r['segment'], product_segments($p), true)) return false;
    if (!empty($r['in_stock']) && empty($p['in_stock'])) return false;
    $price = product_min_price($p);
    if (!empty($r['price_min']) && $price < (int)$r['price_min']) return false;
    if (!empty($r['price_max']) && $price > (int)$r['price_max']) return false;
    $flag = $r['flag'] ?? '';
    if ($flag === 'hit' && empty($p['is_hit']) && empty(sales_counts()[(int)$p['id']])) return false;
    if ($flag === 'sale' && empty($p['discount'])) return false;
    if ($flag === 'new' && !in_array('new', $p['tags'], true)) return false;
    if ($flag === 'sillage' && $p['sillage'] < 4) return false;
    return true;
}

function has_rules(array $r): bool {
    foreach (['gender', 'brand', 'concentration', 'accord', 'season', 'segment', 'flag', 'price_min', 'price_max'] as $k) {
        if (!empty($r[$k])) return true;
    }
    return false;
}

/** id ароматов подборки: ручной список + все, кто подходит под правила */
function collection_ids(array $c): array {
    $cat = catalog();
    $ids = array_values(array_filter(array_map('intval', (array)($c['ids'] ?? [])), fn($id) => isset($cat[$id])));
    $rules = (array)($c['rules'] ?? []);
    if (has_rules($rules)) {
        $matched = [];
        foreach ($cat as $p) {
            if (product_matches_rules($p, $rules)) $matched[] = (int)$p['id'];
        }
        // «Новинки» без отмеченных товаров: последние поступления
        if (!$matched && ($rules['flag'] ?? '') === 'new') {
            $fresh = $cat;
            uasort($fresh, fn($a, $b) => avito_newness($b) <=> avito_newness($a));
            $matched = array_slice(array_map(fn($p) => (int)$p['id'], array_values(array_filter($fresh, fn($p) => !empty($p['in_stock'])))), 0, 48);
        }
        $ids = array_values(array_unique(array_merge($ids, $matched)));
    }
    return $ids;
}

function avito_newness(array $p): int {
    return (int)preg_replace('/\D/', '', (string)strrchr((string)($p['avito_url'] ?? ''), '_'));
}

/* ---------- Похожие ароматы ---------- */

function similar_votes(): array {
    return json_load(VOTES_FILE, []);
}

/** По мнению пользователей: ручной список из админки + голоса покупателей */
function similar_by_users(array $p, int $limit = 12): array {
    $cat = catalog();
    $score = [];
    foreach ($p['similar_ids'] as $i => $id) {
        $score[$id] = 1000 - $i;
    }
    foreach ((similar_votes()[(string)$p['id']] ?? []) as $id => $n) {
        $score[(int)$id] = ($score[(int)$id] ?? 0) + (int)$n;
    }
    unset($score[(int)$p['id']]);
    arsort($score);
    return array_slice(array_values(array_filter(array_map(fn($id) => $cat[$id] ?? null, array_keys($score)))), 0, $limit);
}

/** По аккордам и нотам: коэффициент Жаккара */
function similar_by_notes(array $p, int $limit = 12): array {
    $acc = array_map('mb_strtolower', $p['accords']);
    $notes = product_notes($p);
    if (!$acc && !$notes) return [];
    $res = [];
    foreach (catalog() as $q) {
        if ((int)$q['id'] === (int)$p['id']) continue;
        $qa = array_map('mb_strtolower', $q['accords']);
        $qn = product_notes($q);
        if (!$qa && !$qn) continue;
        $s = 0.0;
        if ($acc && $qa) $s += 0.6 * count(array_intersect($acc, $qa)) / count(array_unique(array_merge($acc, $qa)));
        if ($notes && $qn) $s += 0.4 * count(array_intersect($notes, $qn)) / count(array_unique(array_merge($notes, $qn)));
        if ($s >= 0.15) $res[] = [$s + (!empty($q['in_stock']) ? 0.01 : 0), $q];
    }
    usort($res, fn($a, $b) => $b[0] <=> $a[0]);
    return array_slice(array_map(fn($x) => $x[1], $res), 0, $limit);
}

/** Тем, кому нравится аромат, ещё выбирают: покупки в одних заказах */
function also_chosen(array $p, int $limit = 12): array {
    $cat = catalog();
    $score = [];
    foreach (get_orders() as $o) {
        $ids = array_map(fn($it) => (int)($it['id'] ?? 0), $o['items'] ?? []);
        if (!in_array((int)$p['id'], $ids, true)) continue;
        foreach (array_unique($ids) as $id) {
            if ($id !== (int)$p['id'] && isset($cat[$id])) $score[$id] = ($score[$id] ?? 0) + 1;
        }
    }
    arsort($score);
    $list = array_map(fn($id) => $cat[$id], array_keys($score));
    if (count($list) < $limit) {
        // Пока статистики покупок мало — ароматы со схожим характером: общие «нотные» слова в названии
        // (Tobacco, Oud, Rose, Vanille…), та же аудитория и близкая цена, другие бренды
        $mine = name_note_words($p);
        $price = product_min_price($p) ?: 1;
        $cand = [];
        foreach ($cat as $q) {
            $qid = (int)$q['id'];
            if ($qid === (int)$p['id'] || isset($score[$qid]) || empty($q['in_stock']) || $q['brand'] === $p['brand']) continue;
            $shared = count(array_intersect($mine, name_note_words($q)));
            $qp = product_min_price($q) ?: 1;
            $s = $shared * 4
                + ($q['gender'] === $p['gender'] ? 1.5 : ($q['gender'] === 'Унисекс' || $p['gender'] === 'Унисекс' ? 0.5 : -3))
                + 2 * (1 - min(1, abs($qp - $price) / max($qp, $price)))
                + (!empty($q['image']) ? 0.6 : -1)
                + min(popularity($q), 50) / 100
                + (crc32($p['id'] . ':' . $qid) % 1000) / 600; // устойчивый разброс, чтобы списки у разных ароматов не совпадали
            $cand[] = [$s, $q];
        }
        usort($cand, fn($x, $y) => $y[0] <=> $x[0]);
        $list = array_merge($list, array_map(fn($x) => $x[1], array_slice($cand, 0, $limit)));
    }
    return array_slice($list, 0, $limit);
}

/** Слова-ноты из названия: Tobacco Vanille → [tobacco, vanill] */
function name_note_words(array $p): array {
    static $stems = ['oud', 'oudh', 'tobacco', 'tabac', 'vanill', 'rose', 'santal', 'sandal', 'amber', 'ambre', 'musk', 'musc', 'cherry', 'cerise', 'leather', 'cuir', 'iris', 'jasmin', 'vetiver', 'cedar', 'cedre', 'tea', 'the', 'neroli', 'bergamot', 'citrus', 'lime', 'lemon', 'limone', 'orange', 'peach', 'pear', 'apple', 'fig', 'coffee', 'cafe', 'cacao', 'chocolate', 'honey', 'miel', 'incense', 'encens', 'pepper', 'saffron', 'patchouli', 'tonka', 'almond', 'coconut', 'lavender', 'lavande', 'mint', 'aqua', 'acqua', 'sea', 'marine', 'wood', 'bois', 'smoke', 'fleur', 'flower', 'tuberose', 'violet', 'magnolia', 'peony', 'pivoine', 'gardenia', 'osmanthus', 'mandarin', 'grapefruit', 'pomelo', 'ginger', 'cardamom', 'cinnamon', 'rum', 'whisky', 'cognac', 'vodka', 'gin', 'pistachio', 'praline', 'caramel', 'sugar', 'candy', 'berry', 'framboise', 'raspberry', 'plum', 'prune', 'labdanum', 'myrrh', 'benzoin', 'resin', 'moss', 'mousse', 'oak', 'pine', 'cypress', 'sage', 'basil', 'thyme', 'herb', 'green', 'vert', 'ice', 'fresh', 'sun', 'soleil'];
    $words = preg_split('/[^a-z]+/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $p['name']) ?: $p['name']));
    $found = [];
    foreach ($words as $w) {
        if (strlen($w) < 3) continue;
        foreach ($stems as $st) {
            if (str_starts_with($w, $st)) { $found[] = $st === 'oudh' ? 'oud' : $st; break; }
        }
    }
    // Ноты из описания, если заполнены, тоже учитываются
    foreach (array_merge(product_notes($p), array_map('mb_strtolower', $p['accords'])) as $n) $found[] = $n;
    return array_values(array_unique($found));
}

function same_brand(array $p, int $limit = 12): array {
    $list = array_filter(catalog(), fn($q) => $q['brand'] === $p['brand'] && (int)$q['id'] !== (int)$p['id']);
    uasort($list, fn($a, $b) => (!empty($b['in_stock']) <=> !empty($a['in_stock'])) ?: popularity($b) <=> popularity($a));
    return array_slice(array_values($list), 0, $limit);
}

/* ---------- Данные каталога для браузера ---------- */

function catalog_rows(): array {
    $stats = review_stats();
    $rows = [];
    foreach (catalog() as $p) {
        $id = (int)$p['id'];
        $rows[] = [
            $id,
            $p['brand'],
            product_title($p),
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
            avito_newness($p),
            $p['slug'],
            $p['accords'],
            $p['seasons'],
            (float)($stats[$id]['avg'] ?? 0),
            (int)($stats[$id]['count'] ?? 0),
            popularity($p),
            $p['concentration'],
            in_array('new', $p['tags'], true) ? 1 : 0,
        ];
    }
    return $rows;
}

/** Синонимы брендов для поиска («баккара», «шанель»): из админки брендов */
function brand_aliases(): array {
    $out = [];
    foreach (array_keys(brand_list()) as $name) {
        $a = trim((string)brand_info($name)['aliases']);
        if ($a !== '') $out[$name] = $a;
    }
    return $out;
}

/* ---------- Разметка ---------- */

function stars_html(float $rating, string $cls = 'stars'): string {
    $full = (int)round($rating);
    $html = '<span class="' . $cls . '" aria-label="Оценка ' . str_replace('.', ',', (string)$rating) . ' из 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<svg class="icon' . ($i <= $full ? ' is-on' : '') . '"><use href="#i-star"/></svg>';
    }
    return $html . '</span>';
}

/** Карточка товара (та же разметка, что строит app.js — для индексации и работы без JS) */
function card_html(array $p): string {
    $vols = array_filter(['5', '10', '15'], fn($v) => !empty($p['volumes'][$v]));
    $v = $vols ? reset($vols) : '5';
    $price = product_price($p, $v);
    $old = $price < (int)($p['volumes'][$v] ?? 0) ? (int)$p['volumes'][$v] : 0;
    $url = product_url($p);
    $stats = review_stats()[(int)$p['id']] ?? null;
    $badges = '';
    if (!empty($p['discount']) && !empty($p['in_stock'])) $badges .= '<span class="badge badge--sale">−' . (int)$p['discount'] . '%</span>';
    if (!empty($p['is_hit']) && !empty($p['in_stock'])) $badges .= '<span class="badge badge--hit">Хит</span>';
    if (empty($p['in_stock'])) $badges .= '<span class="badge badge--out">Нет в наличии</span>';
    $img = !empty($p['image'])
        ? '<img src="' . h($p['image']) . '" alt="' . h($p['name']) . ' — распив" loading="lazy" width="480" height="480">'
        : '<img src="/assets/img/atomizer.webp" alt="" loading="lazy" width="520" height="520"><span class="card__ph-brand">' . h($p['brand']) . '</span>';
    $vol_html = '';
    foreach (['5', '10', '15'] as $x) {
        $on = !empty($p['volumes'][$x]) && !empty($p['in_stock']);
        $off = ['10' => 3, '15' => 7][$x] ?? 0;
        $vol_html .= '<button type="button" class="vol' . ($x === $v && $on ? ' is-active' : '') . '" data-vol="' . $x . '" data-id="' . (int)$p['id'] . '"' . ($on ? '' : ' disabled') . '>' . $x . ' мл' . ($off && !empty($p['volumes'][$x]) ? '<span class="vol__off">−' . $off . '%</span>' : '') . '</button>';
    }
    return '<article class="card' . (empty($p['in_stock']) ? ' card__out' : '') . '" data-card="' . (int)$p['id'] . '">'
        . '<a href="' . h($url) . '" class="card__media' . (empty($p['image']) ? ' card__media--empty' : '') . '"><div class="card__badges">' . $badges . '</div>' . $img . '</a>'
        . '<button type="button" class="card__fav" data-fav="' . (int)$p['id'] . '" aria-label="В избранное"><svg class="icon"><use href="#i-heart"/></svg></button>'
        . '<a href="' . h(brand_url($p['brand'])) . '" class="card__brand">' . h($p['brand']) . '</a>'
        . '<h3 class="card__name"><a href="' . h($url) . '">' . h(product_title($p)) . '</a></h3>'
        . ($stats ? '<div class="card__rating">' . stars_html($stats['avg']) . '<span>' . $stats['count'] . '</span></div>' : '')
        . '<div class="vols">' . $vol_html . '</div>'
        . '<div class="card__foot">'
        . (!empty($p['in_stock'])
            ? '<div class="price">' . format_price($price) . ($old ? '<s>' . format_price($old) . '</s>' : '') . '<small>за ' . $v . ' мл</small></div><div class="card__actions"><button type="button" class="icon-btn icon-btn--primary" data-add="' . (int)$p['id'] . '" aria-label="В корзину"><svg class="icon"><use href="#i-bag"/></svg></button></div>'
            : '<div class="price"><small>Сейчас нет в наличии</small></div><button type="button" class="card__notify" data-book="' . (int)$p['id'] . '">Забронировать</button>')
        . '</div></article>';
}

function breadcrumbs_html(array $items, string $site): string {
    $html = '<nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a>';
    $ld = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $site . '/']];
    $pos = 2;
    foreach ($items as [$title, $url]) {
        $html .= '<span>/</span>' . ($url ? '<a href="' . h($url) . '">' . h($title) . '</a>' : '<span aria-current="page">' . h($title) . '</span>');
        $ld[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $title] + ($url ? ['item' => $site . $url] : []);
    }
    $html .= '</nav>';
    $html .= '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ld], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    return $html;
}
