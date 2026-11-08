<?php
/**
 * Perfume Space — фронт-контроллер для ЧПУ:
 * /aromat/{slug}/, /brand/{slug}/, /brands/, /category/, /category/{slug}/, /{страница}/, /catalog.js, /sitemap.xml
 */
require_once __DIR__ . '/includes/layout.php';
handle_legacy_redirects();

$path = urldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$path = '/' . trim($path, '/');
$parts = array_values(array_filter(explode('/', $path), 'strlen'));

if ($path === '/catalog.js') {
    header('Content-Type: application/javascript; charset=UTF-8');
    header('Cache-Control: public, max-age=86400');
    echo 'window.PS_PRODUCTS=' . json_encode(catalog_rows(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    exit;
}

if ($path === '/sitemap.xml') {
    require __DIR__ . '/includes/views/sitemap.php';
    exit;
}

// Канонический адрес со слэшем на конце
$raw = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($parts && !str_ends_with($raw, '/')) {
    $q = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $raw . '/' . ($q !== '' ? '?' . $q : ''), true, 301);
    exit;
}

$route = $parts[0] ?? '';
$slug = $parts[1] ?? '';

if ($route === 'aromat' && count($parts) === 2 && ($product = product_by_slug($slug))) {
    require __DIR__ . '/includes/views/product.php';
    exit;
}
if ($route === 'brand' && count($parts) === 2 && ($brand = brand_by_slug($slug))) {
    require __DIR__ . '/includes/views/brand.php';
    exit;
}
if ($route === 'brands' && count($parts) === 1) {
    require __DIR__ . '/includes/views/brands.php';
    exit;
}
if ($route === 'category' && count($parts) === 1) {
    require __DIR__ . '/includes/views/collections.php';
    exit;
}
if ($route === 'category' && count($parts) === 2 && ($collection = collection_by_slug($slug))) {
    require __DIR__ . '/includes/views/collection.php';
    exit;
}
if (count($parts) === 1 && ($info = page_by_slug($route))) {
    require __DIR__ . '/includes/views/page.php';
    exit;
}

require __DIR__ . '/404.php';
