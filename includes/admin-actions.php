<?php
/**
 * Админка: обработчики новых разделов — карточка аромата, бренды, подборки, промокоды, страницы, CSV.
 * Подключается из admin.php после проверки авторизации. Ожидает $act, $products, $config, $reviews, $flash.
 */
require_once __DIR__ . '/catalog.php';

if (!function_exists('post_str')) {
function post_str(string $k, int $max = 5000): string {
    return mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
}

function post_list(string $k): array {
    $v = $_POST[$k] ?? [];
    if (is_string($v)) $v = preg_split('/\s*[,;\n]\s*/u', $v);
    return array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), (array)$v), 'strlen')));
}

/** «12, Tom Ford Lost Cherry, 846» → [12, 846, …]: id или точное/частичное название */
function resolve_ids(string $text, array $products): array {
    $ids = [];
    foreach (preg_split('/\s*[\n;,]\s*/u', $text) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        if (ctype_digit($part)) { $ids[] = (int)$part; continue; }
        foreach ($products as $p) {
            if (mb_strtolower($p['name']) === mb_strtolower($part)) { $ids[] = (int)$p['id']; continue 2; }
        }
        foreach ($products as $p) {
            if (mb_stripos($p['name'], $part) !== false) { $ids[] = (int)$p['id']; break; }
        }
    }
    $valid = array_map(fn($p) => (int)$p['id'], $products);
    return array_values(array_unique(array_filter($ids, fn($id) => in_array($id, $valid, true))));
}

function uploaded_files(string $field): array {
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) return $f ? [$f] : [];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

$act = $act ?? '';

}

/* ---------- Выгрузка CSV для Excel ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    $cols = ['id', 'brand', 'name', 'short_name', 'slug', 'gender', 'price_5', 'price_10', 'price_15', 'discount', 'in_stock', 'is_hit', 'accords', 'notes_top', 'notes_heart', 'notes_base', 'perfumer', 'country', 'year', 'concentration', 'longevity', 'sillage', 'seasons', 'daytime', 'tags', 'description', 'images', 'similar_ids', 'meta_title', 'meta_description'];
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode('Каталог Perfume Space ' . date('Y-m-d') . '.csv'));
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols, ';', '"', '\\');
    foreach ($products as $p) {
        $p = normalize_product($p);
        fputcsv($out, [
            $p['id'], $p['brand'], $p['name'], $p['short_name'], $p['slug'], $p['gender'],
            $p['volumes']['5'] ?? '', $p['volumes']['10'] ?? '', $p['volumes']['15'] ?? '', $p['discount'] ?? 0,
            !empty($p['in_stock']) ? 1 : 0, !empty($p['is_hit']) ? 1 : 0,
            implode(', ', $p['accords']), $p['notes']['top'], $p['notes']['heart'], $p['notes']['base'],
            $p['perfumer'], $p['country'], $p['year'] ?: '', $p['concentration'], $p['longevity'] ?: '', $p['sillage'] ?: '',
            implode(', ', $p['seasons']), implode(', ', $p['daytime']), implode(', ', $p['tags']), $p['description'],
            implode(', ', $p['images']), implode(', ', $p['similar_ids']), $p['meta_title'], $p['meta_description'],
        ], ';', '"', '\\');
    }
    fclose($out);
    exit;
}

/* ---------- Карточка аромата ---------- */
if ($act === 'save_product_full') {
    $pid = (int)($_POST['product_id'] ?? 0);
    foreach ($products as &$p) {
        if ((int)$p['id'] !== $pid) continue;
        $p['brand'] = post_str('brand', 120) ?: $p['brand'];
        $p['name'] = post_str('name', 200) ?: $p['name'];
        $p['short_name'] = post_str('short_name', 200) ?: $p['name'];
        $slug = slugify(post_str('slug', 200));
        if ($slug !== 'item') $p['slug'] = $slug;
        $p['gender'] = in_array($_POST['gender'] ?? '', ['Женские', 'Мужские', 'Унисекс'], true) ? $_POST['gender'] : $p['gender'];
        $vols = [];
        foreach (['5', '10', '15'] as $v) {
            $price = max(0, (int)($_POST['price_' . $v] ?? 0));
            if ($price > 0) $vols[$v] = $price;
        }
        $p['volumes'] = $vols;
        $p['min_price'] = $vols ? min($vols) : 0;
        $p['discount'] = max(0, min(80, (int)($_POST['discount'] ?? 0)));
        $p['in_stock'] = isset($_POST['in_stock']);
        $p['is_hit'] = isset($_POST['is_hit']);
        $p['accords'] = post_list('accords');
        $p['notes'] = ['top' => post_str('notes_top', 500), 'heart' => post_str('notes_heart', 500), 'base' => post_str('notes_base', 500)];
        $p['perfumer'] = post_str('perfumer', 200);
        $p['country'] = post_str('country', 80);
        $p['year'] = max(0, min(2100, (int)($_POST['year'] ?? 0)));
        $p['concentration'] = in_array($_POST['concentration'] ?? '', CONCENTRATIONS, true) ? $_POST['concentration'] : '';
        $p['longevity'] = max(0, min(5, (int)($_POST['longevity'] ?? 0)));
        $p['sillage'] = max(0, min(5, (int)($_POST['sillage'] ?? 0)));
        $p['seasons'] = array_values(array_intersect(SEASONS, (array)($_POST['seasons'] ?? [])));
        $p['daytime'] = array_values(array_intersect(DAYTIME, (array)($_POST['daytime'] ?? [])));
        $p['tags'] = array_values(array_intersect(['niche', 'luxury', 'arabic', 'vintage', 'new'], (array)($_POST['tags'] ?? [])));
        $p['description'] = post_str('description', 6000);
        $p['meta_title'] = post_str('meta_title', 200);
        $p['meta_description'] = post_str('meta_description', 400);
        $p['similar_ids'] = array_values(array_filter(resolve_ids(post_str('similar', 4000), $products), fn($id) => $id !== $pid));

        $images = array_values(array_filter((array)($p['images'] ?? [])));
        $images = array_values(array_diff($images, (array)($_POST['remove_images'] ?? [])));
        if (!empty($_POST['remove_main'])) $p['image'] = '';
        $main = save_upload($_FILES['image_main'] ?? []);
        if ($main !== '') {
            if (!empty($p['image'])) array_unshift($images, $p['image']);
            $p['image'] = $main;
        }
        foreach (uploaded_files('images_extra') as $file) {
            $u = save_upload($file);
            if ($u !== '') $images[] = $u;
        }
        if (empty($p['image']) && $images) $p['image'] = array_shift($images);
        $p['images'] = array_values(array_unique($images));
        $flash = "Карточка «{$p['name']}» сохранена.";
        break;
    }
    unset($p);
    save_products($products);
}

/* ---------- CSV ---------- */

if ($act === 'import_csv') {
    $file = $_FILES['csv'] ?? null;
    if ($file && ($file['error'] ?? 1) === UPLOAD_ERR_OK) {
        $raw = (string)file_get_contents($file['tmp_name']);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
        $lines = preg_split('/\r\n|\n|\r/', $raw);
        $delim = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $head = array_map('trim', (array)fgetcsv($fh, 0, $delim, '"', '\\'));
        $index = [];
        foreach ($products as $i => $p) $index[(int)$p['id']] = $i;
        $updated = 0;
        while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            if (count($row) < 2) continue;
            $r = array_combine($head, array_pad(array_slice($row, 0, count($head)), count($head), ''));
            $id = (int)($r['id'] ?? 0);
            if (!$r || !isset($index[$id])) continue;
            $p = &$products[$index[$id]];
            $split = fn($v) => array_values(array_filter(array_map('trim', preg_split('/\s*[,|]\s*/u', (string)$v)), 'strlen'));
            foreach (['brand', 'name', 'short_name', 'perfumer', 'country', 'concentration', 'description', 'meta_title', 'meta_description'] as $k) {
                if (array_key_exists($k, $r)) $p[$k] = trim((string)$r[$k]);
            }
            if (!empty($r['slug'])) $p['slug'] = slugify($r['slug']);
            if (isset($r['gender']) && in_array($r['gender'], ['Женские', 'Мужские', 'Унисекс'], true)) $p['gender'] = $r['gender'];
            $vols = [];
            foreach (['5', '10', '15'] as $v) {
                if (array_key_exists('price_' . $v, $r)) { if ((int)$r['price_' . $v] > 0) $vols[$v] = (int)$r['price_' . $v]; }
                elseif (!empty($p['volumes'][$v])) $vols[$v] = $p['volumes'][$v];
            }
            $p['volumes'] = $vols;
            $p['min_price'] = $vols ? min($vols) : 0;
            foreach (['discount', 'year', 'longevity', 'sillage'] as $k) if (array_key_exists($k, $r)) $p[$k] = (int)$r[$k];
            foreach (['in_stock', 'is_hit'] as $k) if (array_key_exists($k, $r)) $p[$k] = in_array(mb_strtolower(trim((string)$r[$k])), ['1', 'да', 'yes', 'true', '+'], true);
            foreach (['accords', 'seasons', 'daytime', 'tags', 'images'] as $k) if (array_key_exists($k, $r)) $p[$k] = $split($r[$k]);
            if (array_key_exists('similar_ids', $r)) $p['similar_ids'] = array_map('intval', $split($r['similar_ids']));
            $p['notes'] = [
                'top' => trim((string)($r['notes_top'] ?? ($p['notes']['top'] ?? ''))),
                'heart' => trim((string)($r['notes_heart'] ?? ($p['notes']['heart'] ?? ''))),
                'base' => trim((string)($r['notes_base'] ?? ($p['notes']['base'] ?? ''))),
            ];
            unset($p);
            $updated++;
        }
        fclose($fh);
        save_products($products);
        $flash = "Импорт завершён: обновлено ароматов — {$updated}.";
    } else {
        $flash = 'Выберите CSV-файл для импорта.';
    }
}

/* ---------- Бренды ---------- */
if ($act === 'save_brand') {
    $name = post_str('brand_name', 120);
    $data = json_load(BRANDS_FILE, []);
    $b = $data[$name] ?? [];
    foreach (['description' => 2000, 'seo_text' => 8000, 'country' => 80, 'aliases' => 500, 'meta_title' => 200, 'meta_description' => 400] as $k => $max) {
        $b[$k] = post_str($k, $max);
    }
    $b['segment'] = array_key_exists($_POST['segment'] ?? '', SEGMENTS) ? $_POST['segment'] : '';
    $slug = slugify(post_str('slug', 120));
    $b['slug'] = $slug !== 'item' ? $slug : slugify($name);
    if (!empty($_POST['remove_logo'])) $b['logo'] = '';
    $logo = save_upload($_FILES['logo'] ?? [], ['jpg', 'jpeg', 'png', 'webp']);
    if ($logo !== '') $b['logo'] = $logo;
    $data[$name] = $b;
    json_save(BRANDS_FILE, $data);
    $flash = "Бренд {$name} сохранён.";
}

/* ---------- Подборки / SEO-страницы ---------- */
if ($act === 'save_collection') {
    $list = json_load(COLLECTIONS_FILE, []);
    $orig = post_str('orig_slug', 120);
    $slug = slugify(post_str('slug', 120) ?: post_str('title', 120));
    foreach ($list as $c) {
        if (($c['slug'] ?? '') === $slug && $slug !== $orig) { $slug .= '-' . substr((string)time(), -4); break; }
    }
    $c = [
        'slug' => $slug,
        'title' => post_str('title', 160) ?: 'Новая подборка',
        'h1' => post_str('h1', 200),
        'menu' => array_values(array_intersect(MENU_GROUPS, (array)($_POST['menu'] ?? []))),
        'rules' => array_filter([
            'gender' => array_values(array_intersect(['Женские', 'Мужские', 'Унисекс'], (array)($_POST['r_gender'] ?? []))),
            'brand' => post_list('r_brand'),
            'concentration' => post_str('r_concentration', 60),
            'accord' => post_str('r_accord', 60),
            'season' => post_str('r_season', 20),
            'segment' => post_str('r_segment', 20),
            'flag' => post_str('r_flag', 20),
            'price_min' => (int)($_POST['r_price_min'] ?? 0),
            'price_max' => (int)($_POST['r_price_max'] ?? 0),
            'in_stock' => isset($_POST['r_in_stock']),
        ]),
        'ids' => resolve_ids(post_str('ids', 20000), $products),
        'sort' => (int)($_POST['sort'] ?? 100),
        'active' => isset($_POST['active']),
        'home_block' => isset($_POST['home_block']),
        'default_sort' => in_array($_POST['default_sort'] ?? '', ['popular', 'new', 'price_asc', 'price_desc', 'rating'], true) ? $_POST['default_sort'] : '',
        'intro' => post_str('intro', 1000),
        'seo_text' => post_str('seo_text', 12000),
        'meta_title' => post_str('meta_title', 200),
        'meta_description' => post_str('meta_description', 400),
        'image' => post_str('image_current', 300),
    ];
    if (!empty($_POST['remove_image'])) $c['image'] = '';
    $img = save_upload($_FILES['image'] ?? []);
    if ($img !== '') $c['image'] = $img;
    $found = false;
    foreach ($list as $i => $old) {
        if (($old['slug'] ?? '') === $orig && $orig !== '') { $list[$i] = $c; $found = true; break; }
    }
    if (!$found) $list[] = $c;
    json_save(COLLECTIONS_FILE, $list);
    $flash = "Подборка «{$c['title']}» сохранена: /category/{$slug}/";
    $_GET['slug'] = $slug;
}

if ($act === 'delete_collection') {
    $slug = post_str('slug', 120);
    json_save(COLLECTIONS_FILE, array_values(array_filter(json_load(COLLECTIONS_FILE, []), fn($c) => ($c['slug'] ?? '') !== $slug)));
    $flash = 'Подборка удалена.';
    unset($_GET['slug']);
}

/* ---------- Промокоды ---------- */
if ($act === 'save_promo') {
    $code = mb_strtoupper(preg_replace('/[^\p{L}\p{N}_-]/u', '', post_str('code', 40)));
    if ($code !== '') {
        $orig = mb_strtoupper(post_str('orig_code', 40));
        $item = [
            'code' => $code,
            'title' => post_str('title', 120),
            'type' => ($_POST['type'] ?? '') === 'amount' ? 'amount' : 'percent',
            'value' => max(0, (int)($_POST['value'] ?? 0)),
            'min_sum' => max(0, (int)($_POST['min_sum'] ?? 0)),
            'expires' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['expires'] ?? '') ? $_POST['expires'] : '',
            'max_uses' => max(0, (int)($_POST['max_uses'] ?? 0)),
            'once' => isset($_POST['once']),
            'first_order' => isset($_POST['first_order']),
            'active' => isset($_POST['active']),
            'show_on_home' => isset($_POST['show_on_home']),
            'lead' => post_str('lead', 160),
            'text' => post_str('text', 400),
            'used' => 0,
        ];
        if ($item['type'] === 'percent') $item['value'] = min(90, $item['value']);
        $item['percent'] = $item['type'] === 'percent' ? $item['value'] : 0;
        $codes = $config['promo_codes'] ?? [];
        $done = false;
        foreach ($codes as $i => $old) {
            if (mb_strtoupper((string)$old['code']) === ($orig ?: $code)) {
                $item['used'] = (int)($old['used'] ?? 0);
                $codes[$i] = $item;
                $done = true;
                break;
            }
        }
        if (!$done) $codes[] = $item;
        $config['promo_codes'] = array_values($codes);
        save_site_config($config);
        $flash = "Промокод {$code} сохранён.";
    }
}

if ($act === 'delete_promo') {
    $code = mb_strtoupper(post_str('code', 40));
    $config['promo_codes'] = array_values(array_filter($config['promo_codes'] ?? [], fn($p) => mb_strtoupper((string)$p['code']) !== $code));
    save_site_config($config);
    $flash = "Промокод {$code} удалён.";
}

/* ---------- Страницы ---------- */
if ($act === 'save_page') {
    $pages = json_load(PAGES_FILE, []);
    $orig = post_str('orig_slug', 120);
    $slug = slugify(post_str('slug', 120) ?: post_str('title', 120));
    $reserved = ['aromat', 'brand', 'brands', 'category', 'admin', 'api', 'assets', 'data', 'includes', 'catalog-js', 'sitemap'];
    if (in_array($slug, $reserved, true)) $slug .= '-page';
    $page = null;
    foreach ($pages as $p) if (($p['slug'] ?? '') === $orig) $page = $p;
    $page = array_merge($page ?? ['documents' => []], [
        'slug' => $slug,
        'title' => post_str('title', 160) ?: 'Новая страница',
        'group' => in_array($_POST['group'] ?? '', ['Покупателям', 'Документы', 'Скрытая'], true) ? $_POST['group'] : 'Покупателям',
        'sort' => (int)($_POST['sort'] ?? 100),
        'lead' => post_str('lead', 600),
        'body' => post_str('body', 30000),
        'meta_title' => post_str('meta_title', 200),
        'meta_description' => post_str('meta_description', 400),
    ]);
    $docs = [];
    foreach ((array)($page['documents'] ?? []) as $i => $d) {
        if (!empty($_POST['remove_doc'][$i])) continue;
        $d['title'] = trim((string)($_POST['doc_title'][$i] ?? $d['title']));
        $docs[] = $d;
    }
    foreach (uploaded_files('docs_new') as $file) {
        $u = save_upload($file, ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
        if ($u !== '') $docs[] = ['title' => post_str('doc_new_title', 160) ?: pathinfo($file['name'], PATHINFO_FILENAME), 'file' => $u];
    }
    $page['documents'] = $docs;
    $found = false;
    foreach ($pages as $i => $p) {
        if (($p['slug'] ?? '') === $orig && $orig !== '') { $pages[$i] = $page; $found = true; break; }
    }
    if (!$found) $pages[] = $page;
    json_save(PAGES_FILE, $pages);
    $flash = "Страница «{$page['title']}» сохранена: /{$slug}/";
    $_GET['slug'] = $slug;
}

if ($act === 'delete_page') {
    $slug = post_str('slug', 120);
    json_save(PAGES_FILE, array_values(array_filter(json_load(PAGES_FILE, []), fn($p) => ($p['slug'] ?? '') !== $slug)));
    $flash = 'Страница удалена.';
    unset($_GET['slug']);
}
