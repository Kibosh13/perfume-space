<?php
/**
 * Админка: настройки сайта (тексты, SEO, контакты, интеграции), заказы, статьи.
 * Ожидает $act, $config, $orders, $products, $flash.
 */

/* ---------- Настройки: поля формы называются cfg[раздел][ключ] ---------- */
if ($act === 'save_settings') {
    $in = (array)($_POST['cfg'] ?? []);
    $allowed = ['hero_badge', 'hero_title', 'hero_subtitle', 'work_hours', 'about_text', 'delivery_text', 'promos', 'contacts', 'legal', 'integrations', 'seo'];
    $ints = ['gift_threshold', 'bundle_min_items', 'bundle_discount_percent', 'loyalty_percent', 'loyalty_max_share'];
    $clean = function ($v, $key = '') use (&$clean, $ints) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $x) $out[$k] = $clean($x, (string)$k);
            return $out;
        }
        $v = mb_substr(trim((string)$v), 0, 10000);
        return in_array($key, $ints, true) ? max(0, (int)$v) : $v;
    };
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $in)) continue;
        $val = $clean($in[$k], $k);
        $config[$k] = is_array($val) ? array_replace_recursive((array)($config[$k] ?? []), $val) : $val;
    }
    if (isset($config['promos']['bundle_discount_percent'])) $config['promos']['bundle_discount_percent'] = min(90, (int)$config['promos']['bundle_discount_percent']);
    if (isset($config['promos']['loyalty_max_share'])) $config['promos']['loyalty_max_share'] = min(100, (int)$config['promos']['loyalty_max_share']);

    // Вопросы и ответы на главной
    if (isset($_POST['faq_q']) && is_array($_POST['faq_q'])) {
        $faq = [];
        foreach ($_POST['faq_q'] as $i => $q) {
            $q = trim((string)$q);
            $a = trim((string)($_POST['faq_a'][$i] ?? ''));
            if ($q !== '' && $a !== '') $faq[] = ['q' => mb_substr($q, 0, 300), 'a' => mb_substr($a, 0, 3000)];
        }
        $config['faq'] = $faq;
    }

    // Картинка для соцсетей (og:image) главной
    $og = save_upload($_FILES['og_image'] ?? []);
    if ($og !== '') $config['seo']['home']['og_image'] = $og;

    $pass = trim((string)($_POST['new_admin_password'] ?? ''));
    $pass_msg = '';
    if ($pass !== '') {
        if (mb_strlen($pass) < 8) {
            $pass_msg = ' Пароль не изменён: нужно минимум 8 символов.';
        } else {
            $config['admin_password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            $pass_msg = ' Пароль админки изменён.';
        }
    }
    save_site_config($config);
    $flash = 'Настройки сохранены.' . $pass_msg;
}

/* ---------- Заказы ---------- */
if ($act === 'update_order') {
    $oid = trim((string)($_POST['order_id'] ?? ''));
    foreach ($orders as &$o) {
        if (($o['id'] ?? '') !== $oid) continue;
        $old = order_status($o);
        $new = array_key_exists($_POST['status'] ?? '', ORDER_STATUSES) ? $_POST['status'] : $old;
        $o['status'] = $new;
        $o['track'] = mb_substr(trim((string)($_POST['track'] ?? '')), 0, 80);
        $o['manager_note'] = mb_substr(trim((string)($_POST['manager_note'] ?? '')), 0, 3000);
        if ($new !== $old) {
            $o['history'][] = ['at' => date('d.m.Y H:i'), 'text' => 'Статус: ' . ORDER_STATUSES[$old] . ' → ' . ORDER_STATUSES[$new]];
        }
        $flash = "Заказ {$oid} сохранён.";
        break;
    }
    unset($o);
    save_orders($orders);
}

if ($act === 'delete_order') {
    $oid = trim((string)($_POST['order_id'] ?? ''));
    $orders = array_values(array_filter($orders, fn($o) => ($o['id'] ?? '') !== $oid));
    save_orders($orders);
    $flash = "Запись {$oid} удалена.";
    unset($_GET['id']);
}

/* ---------- Статьи ---------- */
if ($act === 'save_article') {
    $list = json_load(ARTICLES_FILE, []);
    $orig = trim((string)($_POST['orig_slug'] ?? ''));
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200) ?: 'Новая статья';
    $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: $title);
    foreach ($list as $x) {
        if (($x['slug'] ?? '') === $slug && $slug !== $orig) { $slug .= '-' . substr((string)time(), -4); break; }
    }
    $prev = [];
    foreach ($list as $x) if (($x['slug'] ?? '') === $orig) $prev = $x;
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
    $art = [
        'slug' => $slug,
        'title' => $title,
        'category' => mb_substr(trim((string)($_POST['category'] ?? '')), 0, 80),
        'date' => $date,
        'updated' => date('Y-m-d'),
        'published' => isset($_POST['published']),
        'cover' => $prev['cover'] ?? '',
        'excerpt' => mb_substr(trim((string)($_POST['excerpt'] ?? '')), 0, 600),
        'body' => mb_substr((string)($_POST['body'] ?? ''), 0, 100000),
        'products' => resolve_ids((string)($_POST['products'] ?? ''), $products),
        'images' => $prev['images'] ?? [],
        'meta_title' => mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 200),
        'meta_description' => mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 400),
    ];
    if (!empty($_POST['remove_cover'])) $art['cover'] = '';
    $cover = save_upload($_FILES['cover'] ?? []);
    if ($cover !== '') $art['cover'] = $cover;
    foreach (uploaded_files('body_images') as $file) {
        $u = save_upload($file);
        if ($u !== '') $art['images'][] = $u;
    }
    $found = false;
    foreach ($list as $i => $x) {
        if (($x['slug'] ?? '') === $orig && $orig !== '') { $list[$i] = $art; $found = true; break; }
    }
    if (!$found) $list[] = $art;
    json_save(ARTICLES_FILE, $list);
    $flash = "Статья «{$title}» сохранена" . ($art['published'] ? '' : ' как черновик') . '.';
    $_GET['slug'] = $slug;
}

if ($act === 'delete_article') {
    $slug = trim((string)($_POST['slug'] ?? ''));
    json_save(ARTICLES_FILE, array_values(array_filter(json_load(ARTICLES_FILE, []), fn($x) => ($x['slug'] ?? '') !== $slug)));
    $flash = 'Статья удалена.';
    unset($_GET['slug']);
}
