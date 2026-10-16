<?php
/**
 * Perfume Space — Простая панель управления без CMS (Цены, Товары, Тексты, Заказы)
 */
session_start();
require_once __DIR__ . '/includes/functions.php';

$config = get_site_config();
$products = get_products();
$orders = get_orders();

$default_pass = 'perfume2026';
$flash = '';

// Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['ps_admin_auth']);
    header('Location: /admin.php');
    exit;
}

// Login check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $entered = trim((string)($_POST['password'] ?? ''));
    $saved_hash = $config['admin_password_hash'] ?? '';
    $valid = $saved_hash !== '' ? password_verify($entered, $saved_hash) : ($entered === $default_pass);
    if ($valid) {
        $_SESSION['ps_admin_auth'] = true;
        header('Location: /admin.php');
        exit;
    } else {
        $flash = 'Неверный пароль администратора.';
    }
}

$is_auth = !empty($_SESSION['ps_admin_auth']);

if ($is_auth && isset($_GET['report'])) {
    $file = __DIR__ . '/reports/Сверка с Авито.xlsx';
    if (is_file($file)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode('Сверка с Авито.xlsx'));
        readfile($file);
        exit;
    }
}

// Handle Authenticated Admin Actions
if ($is_auth && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['admin_action'] ?? '';

    // 1. Save Site Texts, Promos & Contacts
    if ($act === 'save_config') {
        $config['hero_badge'] = trim((string)($_POST['hero_badge'] ?? $config['hero_badge']));
        $config['hero_title'] = trim((string)($_POST['hero_title'] ?? $config['hero_title']));
        $config['hero_subtitle'] = trim((string)($_POST['hero_subtitle'] ?? $config['hero_subtitle']));
        $config['work_hours'] = trim((string)($_POST['work_hours'] ?? $config['work_hours']));
        $config['about_text'] = trim((string)($_POST['about_text'] ?? $config['about_text']));
        $config['delivery_text'] = trim((string)($_POST['delivery_text'] ?? $config['delivery_text']));

        $config['promos']['gift_threshold'] = max(0, (int)($_POST['gift_threshold'] ?? 8000));
        $config['promos']['gift_title'] = trim((string)($_POST['gift_title'] ?? ''));
        $config['promos']['gift_desc'] = trim((string)($_POST['gift_desc'] ?? ''));
        $config['promos']['bundle_min_items'] = max(1, (int)($_POST['bundle_min_items'] ?? 5));
        $config['promos']['bundle_discount_percent'] = max(0, min(90, (int)($_POST['bundle_discount_percent'] ?? 10)));
        $config['promos']['bundle_title'] = trim((string)($_POST['bundle_title'] ?? ''));
        $config['promos']['loyalty_desc'] = trim((string)($_POST['loyalty_desc'] ?? ''));

        $config['contacts']['email'] = trim((string)($_POST['email'] ?? ''));
        $config['contacts']['phone'] = trim((string)($_POST['phone'] ?? '[заполнить]'));
        $config['contacts']['telegram'] = trim((string)($_POST['telegram'] ?? '[заполнить]'));
        $config['contacts']['whatsapp'] = trim((string)($_POST['whatsapp'] ?? '[заполнить]'));
        $config['contacts']['vk'] = trim((string)($_POST['vk'] ?? '[заполнить]'));
        $config['contacts']['avito_url'] = trim((string)($_POST['avito_url'] ?? ''));

        $config['legal']['entity'] = trim((string)($_POST['entity'] ?? 'ИП Спивак Яна Игоревна'));
        $config['legal']['inn'] = trim((string)($_POST['inn'] ?? '366109155916'));
        $config['legal']['ogrnip'] = trim((string)($_POST['ogrnip'] ?? '[заполнить]'));
        $config['legal']['address'] = trim((string)($_POST['address'] ?? ''));
        $config['legal']['declaration_number'] = trim((string)($_POST['declaration_number'] ?? '[заполнить]'));

        $config['integrations']['order_email'] = trim((string)($_POST['order_email'] ?? ''));
        $config['integrations']['telegram_bot_token'] = trim((string)($_POST['telegram_bot_token'] ?? '[заполнить]'));
        $config['integrations']['telegram_chat_id'] = trim((string)($_POST['telegram_chat_id'] ?? '[заполнить]'));
        $config['integrations']['yandex_metrika_id'] = trim((string)($_POST['yandex_metrika_id'] ?? '99999999'));
        $config['integrations']['yandex_webmaster_code'] = trim((string)($_POST['yandex_webmaster_code'] ?? '[заполнить]'));

        // Reviews update
        if (isset($_POST['rev_text']) && is_array($_POST['rev_text'])) {
            foreach ($_POST['rev_text'] as $i => $rText) {
                if (isset($config['reviews'][$i])) {
                    $config['reviews'][$i]['author'] = trim((string)($_POST['rev_author'][$i] ?? $config['reviews'][$i]['author']));
                    $config['reviews'][$i]['perfume'] = trim((string)($_POST['rev_perfume'][$i] ?? $config['reviews'][$i]['perfume']));
                    $config['reviews'][$i]['text'] = trim((string)$rText);
                }
            }
        }

        if (!empty($_POST['new_admin_password'])) {
            $config['admin_password_hash'] = password_hash(trim((string)$_POST['new_admin_password']), PASSWORD_DEFAULT);
        }

        save_site_config($config);
        $flash = 'Настройки, тексты и контакты сайта успешно сохранены!';
    }

    // 2. Update Single Product Prices / Availability
    if ($act === 'update_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        foreach ($products as &$p) {
            if ((int)$p['id'] === $pid) {
                $p['brand'] = trim((string)($_POST['brand'] ?? $p['brand']));
                $p['name'] = trim((string)($_POST['name'] ?? $p['name']));
                $p['short_name'] = trim((string)($_POST['short_name'] ?? $p['short_name']));
                $p['gender'] = trim((string)($_POST['gender'] ?? $p['gender']));
                $p['family'] = trim((string)($_POST['family'] ?? $p['family']));

                $p5 = max(0, (int)($_POST['price_5'] ?? 0));
                $p10 = max(0, (int)($_POST['price_10'] ?? 0));
                $p15 = max(0, (int)($_POST['price_15'] ?? 0));
                $vols = [];
                if ($p5 > 0) $vols['5'] = $p5;
                if ($p10 > 0) $vols['10'] = $p10;
                if ($p15 > 0) $vols['15'] = $p15;
                $p['volumes'] = $vols;
                $p['min_price'] = !empty($vols) ? min($vols) : 0;

                $p['discount'] = max(0, min(80, (int)($_POST['discount'] ?? 0)));
                $p['in_stock'] = isset($_POST['in_stock']);
                $p['is_hit'] = isset($_POST['is_hit']);
                $p['is_starter'] = isset($_POST['is_starter']);
                break;
            }
        }
        unset($p);
        save_products($products);
        $flash = "Товар #{$pid} успешно обновлён!";
    }

    // 3. Add New Product
    if ($act === 'add_product') {
        $brand = trim((string)($_POST['brand'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        if ($brand !== '' && $name !== '') {
            $max_id = 0;
            foreach ($products as $item) {
                if ((int)$item['id'] > $max_id) $max_id = (int)$item['id'];
            }
            $new_id = $max_id + 1;
            $p5 = max(0, (int)($_POST['price_5'] ?? 0));
            $p10 = max(0, (int)($_POST['price_10'] ?? 0));
            $p15 = max(0, (int)($_POST['price_15'] ?? 0));
            $vols = [];
            if ($p5 > 0) $vols['5'] = $p5;
            if ($p10 > 0) $vols['10'] = $p10;
            if ($p15 > 0) $vols['15'] = $p15;

            array_unshift($products, [
                'id'          => $new_id,
                'slug'        => 'perfume-' . $new_id,
                'brand'       => $brand,
                'name'        => $name,
                'short_name'  => $name,
                'gender'      => trim((string)($_POST['gender'] ?? 'Унисекс')),
                'family'      => trim((string)($_POST['family'] ?? 'Древесные')),
                'notes'       => [
                    'top'   => trim((string)($_POST['notes_top'] ?? 'Бергамот, Пряности')),
                    'heart' => trim((string)($_POST['notes_heart'] ?? 'Роза, Пачули, Амбра')),
                    'base'  => trim((string)($_POST['notes_base'] ?? 'Сандал, Мускус, Ваниль'))
                ],
                'volumes'     => $vols,
                'old_volumes' => [],
                'min_price'   => !empty($vols) ? min($vols) : 0,
                'in_stock'    => isset($_POST['in_stock']),
                'is_starter'  => true,
                'is_hit'      => isset($_POST['is_hit']),
                'discount'    => max(0, (int)($_POST['discount'] ?? 0))
            ]);
            save_products($products);
            $flash = "Новый аромат «{$name}» добавлен в каталог!";
        }
    }

    // 4. Delete Product
    if ($act === 'delete_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $products = array_values(array_filter($products, fn($x) => (int)$x['id'] !== $pid));
        save_products($products);
        $flash = "Товар #{$pid} удалён.";
    }

    // 5. Toggle Order Status (Active <-> Completed)
    if ($act === 'toggle_order_status') {
        $oid = trim((string)($_POST['order_id'] ?? ''));
        foreach ($orders as &$o) {
            if (($o['id'] ?? '') === $oid) {
                $o['status'] = ($o['status'] === 'active') ? 'completed' : 'active';
                break;
            }
        }
        unset($o);
        save_orders($orders);
        $flash = "Статус заказа {$oid} изменён!";
    }
}

$tab = $_GET['tab'] ?? 'products';
$q = trim((string)($_GET['q'] ?? ''));
$bFilter = trim((string)($_GET['brand'] ?? ''));

$filtered_products = array_filter($products, function ($p) use ($q, $bFilter) {
    if ($bFilter !== '' && ($p['brand'] ?? '') !== $bFilter) return false;
    if ($q !== '' && mb_stripos(($p['name'] ?? '') . ' ' . ($p['brand'] ?? ''), $q, 0, 'UTF-8') === false) return false;
    return true;
});
$display_products = array_slice($filtered_products, 0, 60);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Админ-панель — Perfume Space</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=3">
  <style>
    .admin-wrap { padding: 48px 32px 80px; }
    :root { --blue-ice: var(--blue-soft); --text-secondary: var(--text-2); --text-muted: var(--mute); --bg-card: var(--ink-2); --border-subtle: var(--line); --radius-lg: 8px; --radius-md: 6px; --font-serif: var(--serif); }
    .catalog-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
    .tab-chip { display: inline-flex; align-items: center; height: 40px; padding: 0 18px; border: 1px solid var(--line-2); border-radius: 20px; font-size: 13px; color: var(--text-2); }
    .tab-chip.active, .tab-chip:hover { background: var(--text); color: var(--ink); border-color: var(--text); }
    .form-grid { display: flex; flex-direction: column; gap: 14px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--mute); }
    .two-col-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .section-title { font-family: var(--serif); font-size: 40px; font-weight: 500; }
    .p-badge { display: inline-block; padding: 3px 10px; border-radius: 2px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
    .p-badge--hit { background: var(--blue); color: #fff; }
    .zn-badge { display: inline-block; padding: 3px 10px; border-radius: 2px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; background: rgba(74,222,128,.15); color: var(--ok); }
    .brand-logo { display: inline-flex; align-items: center; gap: 12px; } .brand-logo__img { height: 40px; } .brand-logo__text { display: flex; flex-direction: column; }
    .brand-logo__name { font-family: var(--serif); font-size: 22px; letter-spacing: .12em; text-transform: uppercase; } .brand-logo__sub { font-size: 10px; letter-spacing: .2em; text-transform: uppercase; color: var(--mute); }
    @media (max-width: 760px) { .brand-logo__sub { display: none; } .brand-logo__name { font-size: 16px; white-space: nowrap; } .two-col-grid { grid-template-columns: 1fr; } .admin-wrap { padding: 32px 16px; } }
    .admin-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px; }
    .admin-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .admin-table th, .admin-table td { padding: 10px 8px; border-bottom: 1px solid var(--border-subtle); text-align: left; vertical-align: middle; }
    .admin-table th { color: var(--blue-ice); font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.06em; }
    .admin-input-sm { background: #05070d; border: 1px solid var(--border-subtle); border-radius: 6px; padding: 6px 8px; color: #fff; width: 100%; font-size: 0.82rem; }
    .flash-banner { background: rgba(16, 185, 129, 0.16); border: 1px solid rgba(16, 185, 129, 0.5); color: #34d399; padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; font-weight: 600; }
  </style>
</head>
<body>

  <header class="header">
    <div class="container header__inner" style="grid-template-columns:auto 1fr">
      <a href="/" class="brand-logo">
        <img src="/assets/img/logo-white.png" alt="Perfume Space" class="brand-logo__img" width="46" height="40">
        <div class="brand-logo__text">
          <span class="brand-logo__name">Perfume Space</span>
          <span class="brand-logo__sub">Панель управления сайтом</span>
        </div>
      </a>
      <div style="display:flex;gap:12px;align-items:center;justify-content:flex-end;">
        <a href="/" class="btn btn--line btn--sm">На сайт</a>
        <?php if ($is_auth): ?>
          <a href="/admin.php?logout=1" class="btn btn--ghost btn--sm">Выйти</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="container admin-wrap">
    <h1 class="section-title" style="margin-bottom:20px;">Управление магазином Perfume Space</h1>

    <?php if ($flash !== ''): ?>
      <div class="flash-banner"><?= h($flash) ?></div>
    <?php endif; ?>

    <?php if (!$is_auth): ?>
      <div class="admin-card" style="max-width:460px;margin:40px auto;">
        <h2 style="font-family:var(--font-serif);font-size:1.65rem;margin-bottom:10px;">Вход для владельца сайта</h2>

        <form method="post" class="form-grid">
          <input type="hidden" name="admin_login" value="1">
          <div class="form-group">
            <label>Пароль администратора</label>
            <input type="password" name="password" class="form-control" autocomplete="current-password" required autofocus>
          </div>
          <button type="submit" class="btn btn--primary">Войти в админку</button>
        </form>
      </div>
    <?php else: ?>

      <!-- Admin Navigation Tabs -->
      <div class="catalog-tabs" style="margin-bottom:24px;">
        <a href="/admin.php?tab=products" class="tab-chip <?= $tab === 'products' ? 'active' : '' ?>">1. Каталог и цены (<?= count($products) ?>)</a>
        <a href="/admin.php?tab=texts" class="tab-chip <?= $tab === 'texts' ? 'active' : '' ?>">2. Тексты, акции и контакты ([заполнить])</a>
        <a href="/admin.php?tab=orders" class="tab-chip <?= $tab === 'orders' ? 'active' : '' ?>">3. Заказы и заявки (<?= count($orders) ?>)</a>
        <?php if (is_file(__DIR__ . '/reports/Сверка с Авито.xlsx')): ?><a href="/admin.php?report=1" class="tab-chip">Скачать сверку с Авито (.xlsx)</a><?php endif; ?>
      </div>

      <?php if ($tab === 'products'): ?>
        <!-- Add New Product -->
        <div class="admin-card">
          <h2 style="font-family:var(--font-serif);font-size:1.45rem;margin-bottom:14px;">+ Добавить новый аромат в каталог</h2>
          <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;align-items:end;">
            <input type="hidden" name="admin_action" value="add_product">
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Бренд *</label>
              <input type="text" name="brand" class="admin-input-sm" placeholder="Amouage" required>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Полное название *</label>
              <input type="text" name="name" class="admin-input-sm" placeholder="Amouage Guidance" required>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Категория</label>
              <select name="gender" class="admin-input-sm">
                <option value="Унисекс">Унисекс</option>
                <option value="Женские">Женские</option>
                <option value="Мужские">Мужские</option>
              </select>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Семейство</label>
              <select name="family" class="admin-input-sm">
                <option value="Древесные">Древесные</option>
                <option value="Восточные">Восточные</option>
                <option value="Цветочные">Цветочные</option>
                <option value="Гурманские">Гурманские</option>
                <option value="Цитрусовые">Цитрусовые</option>
                <option value="Пряные">Пряные</option>
                <option value="Кожаные">Кожаные</option>
                <option value="Мускусные">Мускусные</option>
              </select>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">5 мл (₽)</label>
              <input type="number" name="price_5" class="admin-input-sm" placeholder="1500">
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">10 мл (₽)</label>
              <input type="number" name="price_10" class="admin-input-sm" placeholder="3000">
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">15 мл (₽)</label>
              <input type="number" name="price_15" class="admin-input-sm" placeholder="4500">
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
              <label style="font-size:0.8rem;"><input type="checkbox" name="in_stock" checked> В наличии</label>
              <label style="font-size:0.8rem;"><input type="checkbox" name="is_hit"> Хит</label>
            </div>
            <button type="submit" class="btn btn--primary btn--sm">Добавить</button>
          </form>
        </div>

        <!-- Search & Edit Products -->
        <div class="admin-card">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h2 style="font-family:var(--font-serif);font-size:1.45rem;">Редактирование цен и наличия (показано <?= count($display_products) ?> из <?= count($filtered_products) ?>)</h2>
            <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
              <input type="hidden" name="tab" value="products">
              <input type="search" name="q" value="<?= h($q) ?>" class="admin-input-sm" style="width:240px;" placeholder="Поиск по названию или бренду...">
              <button type="submit" class="btn btn--line btn--sm">Найти</button>
              <?php if ($q !== ''): ?>
                <a href="/admin.php?tab=products" class="btn btn--ghost btn--sm">Сброс</a>
              <?php endif; ?>
            </form>
          </div>

          <div style="overflow-x:auto;">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Фото</th>
                  <th>Бренд и Название</th>
                  <th>Пол</th>
                  <th>5 мл (₽)</th>
                  <th>10 мл (₽)</th>
                  <th>15 мл (₽)</th>
                  <th>Скидка %</th>
                  <th>Статус</th>
                  <th>Действие</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($display_products as $p): ?>
                  <tr>
                    <form method="post">
                      <input type="hidden" name="admin_action" value="update_product">
                      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="brand" value="<?= h($p['brand'] ?? '') ?>">
                      <input type="hidden" name="short_name" value="<?= h($p['short_name'] ?? '') ?>">
                      <input type="hidden" name="family" value="<?= h($p['family'] ?? '') ?>">
                      <td><?php if (!empty($p['image'])): ?><img src="<?= h($p['image']) ?>" alt="" width="48" height="48" loading="lazy" style="border-radius:4px;object-fit:cover"><?php else: ?><span class="muted">нет</span><?php endif; ?></td>
                      <td>
                        <div style="font-size:0.72rem;color:var(--blue-ice);font-weight:700;"><?= h($p['brand'] ?? '') ?></div>
                        <input type="text" name="name" value="<?= h($p['name'] ?? '') ?>" class="admin-input-sm" style="min-width:210px;">
                      </td>
                      <td>
                        <select name="gender" class="admin-input-sm">
                          <option value="Унисекс" <?= ($p['gender'] ?? '') === 'Унисекс' ? 'selected' : '' ?>>Унисекс</option>
                          <option value="Женские" <?= ($p['gender'] ?? '') === 'Женские' ? 'selected' : '' ?>>Женские</option>
                          <option value="Мужские" <?= ($p['gender'] ?? '') === 'Мужские' ? 'selected' : '' ?>>Мужские</option>
                        </select>
                      </td>
                      <td><input type="number" name="price_5" value="<?= (int)($p['volumes']['5'] ?? 0) ?>" class="admin-input-sm" style="width:78px;"></td>
                      <td><input type="number" name="price_10" value="<?= (int)($p['volumes']['10'] ?? 0) ?>" class="admin-input-sm" style="width:78px;"></td>
                      <td><input type="number" name="price_15" value="<?= (int)($p['volumes']['15'] ?? 0) ?>" class="admin-input-sm" style="width:78px;"></td>
                      <td><input type="number" name="discount" value="<?= (int)($p['discount'] ?? 0) ?>" class="admin-input-sm" style="width:62px;"></td>
                      <td style="white-space:nowrap;">
                        <label style="display:block;font-size:0.76rem;"><input type="checkbox" name="in_stock" <?= !empty($p['in_stock']) ? 'checked' : '' ?>> В наличии</label>
                        <label style="display:block;font-size:0.76rem;"><input type="checkbox" name="is_hit" <?= !empty($p['is_hit']) ? 'checked' : '' ?>> Хит</label>
                        <label style="display:block;font-size:0.76rem;"><input type="checkbox" name="is_starter" <?= !empty($p['is_starter']) ? 'checked' : '' ?>> Старт-сет</label>
                      </td>
                      <td style="white-space:nowrap;">
                        <button type="submit" class="btn btn--primary btn--sm" style="padding:6px 12px;">Сохранить</button>
                      </td>
                    </form>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'texts'): ?>
        <!-- Edit Site Texts, Contacts & [заполнить] Fields -->
        <form method="post" class="admin-card form-grid">
          <input type="hidden" name="admin_action" value="save_config">
          <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:8px;">Редактирование текстов, акций, реквизитов и полей [заполнить]</h2>

          <div class="two-col-grid">
            <div class="form-group">
              <label>Надзаголовок первого экрана (бейдж)</label>
              <input type="text" name="hero_badge" class="form-control" value="<?= h($config['hero_badge'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>График работы</label>
              <input type="text" name="work_hours" class="form-control" value="<?= h($config['work_hours'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Главный заголовок H1 первого экрана</label>
            <input type="text" name="hero_title" class="form-control" value="<?= h($config['hero_title'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Подзаголовок первого экрана</label>
            <textarea name="hero_subtitle" class="form-control" rows="2"><?= h($config['hero_subtitle'] ?? '') ?></textarea>
          </div>

          <h3 style="margin-top:16px;color:var(--blue-ice);">Настройки акций (Подарок 2 мл от 8 000 ₽ и скидка 10% от 5 позиций)</h3>
          <div class="two-col-grid">
            <div class="form-group">
              <label>Порог суммы для подарка 2 мл (₽)</label>
              <input type="number" name="gift_threshold" class="form-control" value="<?= (int)($config['promos']['gift_threshold'] ?? 8000) ?>">
            </div>
            <div class="form-group">
              <label>Заголовок акции с подарком</label>
              <input type="text" name="gift_title" class="form-control" value="<?= h($config['promos']['gift_title'] ?? '') ?>">
            </div>
          </div>

          <div class="two-col-grid">
            <div class="form-group">
              <label>Минимум позиций для скидки (шт.)</label>
              <input type="number" name="bundle_min_items" class="form-control" value="<?= (int)($config['promos']['bundle_min_items'] ?? 5) ?>">
            </div>
            <div class="form-group">
              <label>Размер скидки за сет (%)</label>
              <input type="number" name="bundle_discount_percent" class="form-control" value="<?= (int)($config['promos']['bundle_discount_percent'] ?? 10) ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Условия накопительной скидки</label>
            <textarea name="loyalty_desc" class="form-control" rows="2"><?= h($config['promos']['loyalty_desc'] ?? '') ?></textarea>
          </div>

          <h3 style="margin-top:16px;color:var(--blue-ice);">Контакты и поля [заполнить]</h3>
          <div class="two-col-grid">
            <div class="form-group">
              <label>Почта на домене (info@)</label>
              <input type="text" name="email" class="form-control" value="<?= h($config['contacts']['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Телефон (замените [заполнить])</label>
              <input type="text" name="phone" class="form-control" value="<?= h($config['contacts']['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Ссылка на Telegram (замените [заполнить])</label>
              <input type="text" name="telegram" class="form-control" value="<?= h($config['contacts']['telegram'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Ссылка на WhatsApp / Мессенджер (замените [заполнить])</label>
              <input type="text" name="whatsapp" class="form-control" value="<?= h($config['contacts']['whatsapp'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Ссылка на группу VK (замените [заполнить])</label>
              <input type="text" name="vk" class="form-control" value="<?= h($config['contacts']['vk'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Ссылка на профиль Авито</label>
              <input type="text" name="avito_url" class="form-control" value="<?= h($config['contacts']['avito_url'] ?? '') ?>">
            </div>
          </div>

          <h3 style="margin-top:16px;color:var(--blue-ice);">Реквизиты ИП и документы</h3>
          <div class="two-col-grid">
            <div class="form-group">
              <label>Наименование ИП</label>
              <input type="text" name="entity" class="form-control" value="<?= h($config['legal']['entity'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>ИНН</label>
              <input type="text" name="inn" class="form-control" value="<?= h($config['legal']['inn'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>ОГРНИП</label>
              <input type="text" name="ogrnip" class="form-control" value="<?= h($config['legal']['ogrnip'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Юридический адрес</label>
              <input type="text" name="address" class="form-control" value="<?= h($config['legal']['address'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Номер декларации соответствия ЕАЭС</label>
              <input type="text" name="declaration_number" class="form-control" value="<?= h($config['legal']['declaration_number'] ?? '') ?>">
            </div>
          </div>

          <h3 style="margin-top:16px;color:var(--blue-ice);">Куда приходят заказы + Яндекс Метрика и Вебмастер</h3>
          <div class="two-col-grid">
            <div class="form-group">
              <label>Email для получения заявок</label>
              <input type="text" name="order_email" class="form-control" value="<?= h($config['integrations']['order_email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Токен Telegram-бота для уведомлений</label>
              <input type="text" name="telegram_bot_token" class="form-control" value="<?= h($config['integrations']['telegram_bot_token'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Telegram Chat ID менеджера</label>
              <input type="text" name="telegram_chat_id" class="form-control" value="<?= h($config['integrations']['telegram_chat_id'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Код подтверждения Яндекс Вебмастера</label>
              <input type="text" name="yandex_webmaster_code" class="form-control" value="<?= h($config['integrations']['yandex_webmaster_code'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Номер счётчика Яндекс Метрики</label>
              <input type="text" name="yandex_metrika_id" class="form-control" value="<?= h($config['integrations']['yandex_metrika_id'] ?? '') ?>">
            </div>
          </div>

          <h3 style="margin-top:16px;color:var(--blue-ice);">Тексты разделов «О нас» и «Доставка»</h3>
          <div class="form-group">
            <label>Текст «О компании»</label>
            <textarea name="about_text" class="form-control" rows="3"><?= h($config['about_text'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Текст «Доставка»</label>
            <textarea name="delivery_text" class="form-control" rows="2"><?= h($config['delivery_text'] ?? '') ?></textarea>
          </div>

          <h3 style="margin-top:16px;color:var(--blue-ice);">Карточки отзывов (можно вставить реальные тексты с Авито)</h3>
          <?php foreach (($config['reviews'] ?? []) as $idx => $rev): ?>
            <div style="background:#05070d;border:1px solid var(--border-subtle);border-radius:10px;padding:14px;">
              <div class="two-col-grid" style="margin-bottom:8px;">
                <input type="text" name="rev_author[<?= $idx ?>]" class="form-control" value="<?= h($rev['author'] ?? '') ?>" placeholder="Автор отзыва">
                <input type="text" name="rev_perfume[<?= $idx ?>]" class="form-control" value="<?= h($rev['perfume'] ?? '') ?>" placeholder="Аромат">
              </div>
              <textarea name="rev_text[<?= $idx ?>]" class="form-control" rows="2"><?= h($rev['text'] ?? '') ?></textarea>
            </div>
          <?php endforeach; ?>

          <button type="submit" class="btn btn--primary" style="margin-top:14px;">Сохранить все изменения на сайте</button>
        </form>

      <?php elseif ($tab === 'orders'): ?>
        <!-- Orders & Leads Management -->
        <div class="admin-card">
          <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:14px;">Поступившие заказы, бронирования и заявки (<?= count($orders) ?>)</h2>
          <div style="overflow-x:auto;">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Номер и Дата</th>
                  <th>Клиент (Имя, Телефон, Почта)</th>
                  <th>Состав заказа / Заявка</th>
                  <th>Доставка и Оплата</th>
                  <th>Сумма</th>
                  <th>Статус</th>
                  <th>Действие</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o): ?>
                  <tr>
                    <td>
                      <strong style="color:#fff;"><?= h($o['id'] ?? '') ?></strong>
                      <div style="font-size:0.75rem;color:var(--text-muted);"><?= h($o['created_at'] ?? '') ?></div>
                    </td>
                    <td>
                      <div style="font-weight:700;color:#fff;"><?= h($o['customer']['name'] ?? '') ?></div>
                      <div><?= h($o['customer']['phone'] ?? '') ?></div>
                      <div style="font-size:0.76rem;color:var(--blue-ice);"><?= h($o['customer']['email'] ?? '') ?></div>
                    </td>
                    <td>
                      <?php if (!empty($o['items'])): ?>
                        <?php foreach ($o['items'] as $it): ?>
                          <div>• <?= h($it['name'] ?? '') ?> (<?= h($it['volume'] ?? '') ?> мл × <?= (int)($it['qty'] ?? 1) ?>)</div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                      <?php if (!empty($o['gift_2ml'])): ?>
                        <div style="color:#34d399;font-weight:700;">+ 🎁 Подарок парфюм 2 мл</div>
                      <?php endif; ?>
                      <?php if (!empty($o['comment'])): ?>
                        <div style="font-size:0.76rem;color:var(--text-secondary);margin-top:4px;"><?= h($o['comment']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div><?= h($o['delivery'] ?? '—') ?></div>
                      <div style="font-size:0.75rem;color:var(--text-muted);"><?= h($o['payment'] ?? '—') ?></div>
                    </td>
                    <td><strong style="color:#fff;"><?= format_price($o['total'] ?? 0) ?></strong></td>
                    <td>
                      <?php if (($o['status'] ?? 'active') === 'active'): ?>
                        <span class="p-badge p-badge--hit">Активный</span>
                      <?php else: ?>
                        <span class="zn-badge">Завершённый</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="post">
                        <input type="hidden" name="admin_action" value="toggle_order_status">
                        <input type="hidden" name="order_id" value="<?= h($o['id'] ?? '') ?>">
                        <button type="submit" class="btn btn--line btn--sm">
                          <?= ($o['status'] ?? 'active') === 'active' ? 'Отметить завершённым ✓' : 'Вернуть в активные' ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</body>
</html>
