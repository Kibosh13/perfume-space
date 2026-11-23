<?php
/**
 * Perfume Space — панель управления сайтом (CRM): заказы, клиенты, каталог, контент, SEO, настройки
 */
session_start();
require_once __DIR__ . '/includes/layout.php';

$config = get_site_config();
$products = get_products();
$orders = get_orders();
$reviews = get_reviews();
$products_all = $products;

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

// Защита форм от подделки запросов (CSRF): токен добавляется во все POST-формы автоматически
if (empty($_SESSION['ps_csrf'])) {
    $_SESSION['ps_csrf'] = bin2hex(random_bytes(16));
}
if ($is_auth && $_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['ps_csrf'], (string)($_POST['_csrf'] ?? ''))) {
    http_response_code(400);
    exit('Форма устарела. Обновите страницу и попробуйте ещё раз.');
}
ob_start(fn($html) => preg_replace('/(<form\b[^>]*method="post"[^>]*>)/i', '$1<input type="hidden" name="_csrf" value="' . $_SESSION['ps_csrf'] . '">', $html));

if ($is_auth && isset($_GET['export']) && $_GET['export'] === 'csv') {
    require_once __DIR__ . '/includes/admin-actions.php';
}

if ($is_auth && isset($_GET['report'])) {
    $file = __DIR__ . '/reports/Сверка с Авито.xlsx';
    if (is_file($file)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode('Сверка с Авито.xlsx'));
        readfile($file);
        exit;
    }
}

// Действия (POST)
if ($is_auth && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['admin_action'] ?? '';
    require __DIR__ . '/includes/admin-actions.php';
    require __DIR__ . '/includes/admin/actions-core.php';
    require __DIR__ . '/includes/admin/actions-crm.php';
    // Возвращаемся на ту же страницу (обычный GET), чтобы обновление страницы не отправляло форму повторно
    if ($flash !== '') {
        $_SESSION['ps_flash'] = $flash;
        $qs = $_GET;
        header('Location: /admin.php' . ($qs ? '?' . http_build_query($qs) : ''));
        exit;
    }
}
if (!empty($_SESSION['ps_flash'])) {
    $flash = $_SESSION['ps_flash'];
    unset($_SESSION['ps_flash']);
}

$tab = $_GET['tab'] ?? 'dashboard';
if ($tab === 'texts') $tab = 'settings';
if ($tab === 'articles' && !ARTICLES_ENABLED) $tab = 'dashboard';
$pending_reviews = count(array_filter($reviews, fn($r) => ($r['status'] ?? '') === 'pending'));
$new_orders = count(array_filter($orders, fn($o) => ($o['type'] ?? 'order') !== 'request' && order_status($o) === 'new'));
$new_requests = count(array_filter($orders, fn($o) => ($o['type'] ?? '') === 'request' && order_status($o) === 'new'));
$menu = [
    'Главное' => [['dashboard', 'Сводка', '', 0]],
    'Продажи' => [['orders', 'Заказы', '', $new_orders], ['orders&type=request', 'Заявки', '', $new_requests], ['customers', 'Клиенты', '', 0], ['promos', 'Промокоды', '', 0]],
    'Каталог' => [['products', 'Ароматы', '', 0], ['brands', 'Бренды', '', 0], ['collections', 'Подборки и SEO-страницы', '', 0], ['reviews', 'Отзывы', '', $pending_reviews]],
    'Контент' => array_values(array_filter([ARTICLES_ENABLED ? ['articles', 'Библиотека парфюмера', '', 0] : null, ['pages', 'Страницы и документы', '', 0]])),
    'Настройки' => [['settings&sub=home', 'Главная и тексты', '', 0], ['settings&sub=promo', 'Акции и бонусы', '', 0], ['settings&sub=seo', 'SEO', '', 0], ['settings&sub=contacts', 'Контакты и реквизиты', '', 0], ['settings&sub=access', 'Уведомления и доступ', '', 0]],
];
$is_active = function (string $key) use ($tab) {
    parse_str('tab=' . $key, $q);
    if (($q['tab'] ?? '') !== $tab && !($q['tab'] === 'products' && $tab === 'product')) return false;
    foreach (['type', 'sub'] as $k) {
        if (isset($q[$k]) && ($_GET[$k] ?? ($k === 'sub' ? 'home' : '')) !== $q[$k]) return false;
        if (!isset($q[$k]) && $k === 'type' && $tab === 'orders' && ($_GET['type'] ?? '') === 'request') return false;
    }
    return true;
};
$q = trim((string)($_GET['q'] ?? ''));
$bFilter = trim((string)($_GET['brand'] ?? ''));
$filtered_products = array_filter($products, function ($p) use ($q, $bFilter) {
    if ($bFilter !== '' && ($p['brand'] ?? '') !== $bFilter) return false;
    if ($q !== '' && mb_stripos(($p['name'] ?? '') . ' ' . ($p['brand'] ?? ''), $q, 0, 'UTF-8') === false) return false;
    if (($_GET['f'] ?? '') === 'out' && !empty($p['in_stock'])) return false;
    if (($_GET['f'] ?? '') === 'nodesc' && (!empty($p['accords']) || !empty($p['description']))) return false;
    if (($_GET['f'] ?? '') === 'nophoto' && !empty($p['image'])) return false;
    return true;
});
$per_page = 50;
$p_pages = max(1, (int)ceil(count($filtered_products) / $per_page));
$p_page = max(1, min($p_pages, (int)($_GET['p'] ?? 1)));
$display_products = array_slice($filtered_products, ($p_page - 1) * $per_page, $per_page);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Админка — Perfume Space</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Manrope:wght@400;500;600;700&family=Caveat:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/assets/css/admin.css') ?>">
</head>
<body class="adm">
<?php if (!$is_auth): ?>
  <main class="adm-login">
    <form method="post" class="admin-card form-grid">
      <a href="/" class="adm-brand"><img src="/assets/img/logo-white.png" alt="" width="46" height="40"><span>Perfume Space</span></a>
      <h1>Вход в панель управления</h1>
      <?php if ($flash !== ''): ?><div class="flash-banner flash-banner--err"><?= h($flash) ?></div><?php endif; ?>
      <input type="hidden" name="admin_login" value="1">
      <div class="form-group"><label>Пароль</label><input type="password" name="password" class="form-control" autocomplete="current-password" required autofocus></div>
      <button type="submit" class="btn btn--primary">Войти</button>
    </form>
  </main>
<?php else: ?>
  <div class="adm-shell">
    <aside class="adm-side" id="admSide">
      <a href="/admin.php" class="adm-brand"><img src="/assets/img/logo-white.png" alt="" width="40" height="34"><span>Perfume Space</span></a>
      <nav>
        <?php foreach ($menu as $group => $items): ?>
          <div class="adm-side__group"><?= h($group) ?></div>
          <?php foreach ($items as [$key, $label, , $badge]): ?>
            <a href="/admin.php?tab=<?= h($key) ?>" class="<?= $is_active($key) ? 'is-active' : '' ?>"><?= h($label) ?><?php if ($badge): ?><b class="adm-badge"><?= $badge ?></b><?php endif; ?></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
      <div class="adm-side__foot">
        <a href="/" target="_blank">Открыть сайт ↗</a>
        <a href="/admin.php?logout=1">Выйти</a>
      </div>
    </aside>
    <div class="adm-main">
      <div class="adm-topbar"><button type="button" class="adm-burger" onclick="document.getElementById('admSide').classList.toggle('is-open')">☰ Меню</button><span>Perfume Space · админка</span></div>
      <?php if (empty($config['admin_password_hash'])): ?>
        <div class="flash-banner flash-banner--warn">Вход по стандартному паролю. <a href="/admin.php?tab=settings&sub=access">Смените пароль</a> — это займёт минуту.</div>
      <?php endif; ?>
      <?php if ($flash !== ''): ?><div class="flash-banner"><?= h($flash) ?></div><?php endif; ?>
      <?php
        $views = ['dashboard' => 'dashboard', 'orders' => 'orders', 'customers' => 'customers', 'articles' => 'articles', 'settings' => 'settings', 'products' => 'products', 'reviews' => 'reviews'];
        if (isset($views[$tab])) {
            if ($tab === 'products') echo '<div class="adm-page-head"><h1>Ароматы <sup class="muted">' . count($products) . '</sup></h1></div>';
            if ($tab === 'reviews') echo '<div class="adm-page-head"><h1>Отзывы</h1></div>';
            include __DIR__ . '/includes/admin/' . $views[$tab] . '.php';
        } elseif (in_array($tab, ['product', 'brands', 'collections', 'promos', 'pages'], true)) {
            include __DIR__ . '/includes/admin-views.php';
        } else {
            include __DIR__ . '/includes/admin/dashboard.php';
        }
      ?>
    </div>
  </div>
<?php endif; ?>
</body>
</html>
