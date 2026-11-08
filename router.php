<?php
/**
 * Роутер для встроенного сервера PHP (локальная разработка):
 * php -S 127.0.0.1:8085 -t . router.php
 * На хостинге то же самое делает .htaccess.
 */
require_once __DIR__ . '/includes/functions.php';
handle_legacy_redirects();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if (str_starts_with($uri, '/includes/') || str_starts_with($uri, '/data/') || str_starts_with($uri, '/reports/') || str_starts_with($uri, '/tools/')) {
    require __DIR__ . '/404.php';
    return true;
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

if ($uri === '/sitemap.xml') {
    require __DIR__ . '/site.php';
    return true;
}

if (file_exists($file) && !is_dir($file)) {
    return false; // статика или существующий .php
}

require __DIR__ . '/site.php';
return true;
