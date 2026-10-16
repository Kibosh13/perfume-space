<?php
/**
 * Local PHP built-in server router (supports 301 redirects, static files, and custom 404.php)
 */
require_once __DIR__ . '/includes/functions.php';
handle_legacy_redirects();

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if (str_starts_with($uri, '/includes/') || (str_starts_with($uri, '/data/') && !str_ends_with($uri, '.xlsx'))) {
    require __DIR__ . '/404.php';
    return true;
}

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // serve static file or existing .php script
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// Custom 404 page
require __DIR__ . '/404.php';
return true;
