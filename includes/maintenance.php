<?php
/**
 * Режим «Сайт закрыт»: посетители видят заглушку (503), администратор и владельцы
 * ссылки предпросмотра — обычный сайт. Включается в админке: Настройки → Закрыть сайт.
 */

function maintenance_cfg(): array {
    return (array)(get_site_config()['maintenance'] ?? []);
}

function maintenance_enabled(): bool {
    return !empty(maintenance_cfg()['enabled']);
}

/** Может ли текущий посетитель видеть сайт, пока он закрыт */
function maintenance_bypass(): bool {
    $token = (string)(maintenance_cfg()['token'] ?? '');
    if ($token !== '') {
        $q = (string)($_GET['preview'] ?? '');
        if ($q !== '' && hash_equals($token, $q)) {
            setcookie('ps_preview', $token, ['expires' => time() + 60 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
            $_COOKIE['ps_preview'] = $token;
        }
        if (hash_equals($token, (string)($_COOKIE['ps_preview'] ?? ''))) return true;
    }
    return is_admin_session();
}

/** Вошёл ли посетитель в админку (без лишнего запуска сессии) */
function is_admin_session(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!isset($_COOKIE[session_name()])) return $ok = false;
    $started = session_status() === PHP_SESSION_ACTIVE;
    if (!$started) @session_start();
    $ok = !empty($_SESSION['ps_admin_auth']);
    if (!$started) session_write_close();
    return $ok;
}

/** Вызывается в начале каждой публичной точки входа */
function maintenance_gate(bool $json = false): void {
    if (!maintenance_enabled() || maintenance_bypass()) return;
    http_response_code(503);
    header('Retry-After: 86400');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if ($json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'Сайт временно закрыт.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    $m = maintenance_cfg();
    $cfg = get_site_config();
    $c = (array)($cfg['contacts'] ?? []);
    $title = trim((string)($m['title'] ?? '')) ?: 'Сайт скоро откроется';
    $text = trim((string)($m['text'] ?? '')) ?: 'Мы готовим обновлённый магазин оригинальной парфюмерии на распив. Совсем скоро всё заработает — а пока с нами можно связаться напрямую.';
    $links = [];
    $c = array_filter($c, fn($x) => is_string($x) && $x !== '' && !str_contains($x, '[заполнить'));
    if (!empty($c['phone'])) $links[] = ['tel:' . preg_replace('/[^\d+]/', '', $c['phone']), $c['phone']];
    foreach (['telegram' => 'Telegram', 'whatsapp' => 'WhatsApp', 'vk' => 'ВКонтакте', 'avito_url' => 'Авито'] as $k => $label) {
        if (!empty($c[$k]) && preg_match('~^https?://~', $c[$k])) $links[] = [$c[$k], $label];
    }
    if (!empty($c['email']) && str_contains($c['email'], '@')) $links[] = ['mailto:' . $c['email'], $c['email']];
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $e($title) ?> — Perfume Space</title>
<link rel="icon" href="/assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;min-height:100%}
  body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 20px;background:#05070c radial-gradient(circle at 50% 0%,rgba(61,100,255,.18),transparent 60%);color:#eef1f7;font-family:Inter,system-ui,sans-serif;text-align:center}
  main{max-width:560px}
  .logo{display:inline-flex;align-items:center;gap:12px;margin-bottom:40px;color:#fff;text-decoration:none}
  .logo img{width:44px;height:auto}
  .logo span{font-family:Caveat,cursive;font-size:34px;font-weight:600;line-height:1}
  h1{font-size:clamp(28px,6vw,40px);line-height:1.15;margin:0 0 18px;font-weight:600}
  p{color:#b5bccb;font-size:17px;line-height:1.6;margin:0 0 32px;white-space:pre-line}
  .links{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
  .links a{color:#fff;text-decoration:none;border:1px solid rgba(196,210,255,.28);border-radius:999px;padding:10px 18px;font-size:15px;transition:background .2s,border-color .2s}
  .links a:hover{background:rgba(61,100,255,.18);border-color:#3d64ff}
</style>
</head>
<body>
<main>
  <div class="logo"><img src="/assets/img/logo-white.png" alt=""><span>Perfume Space</span></div>
  <h1><?= $e($title) ?></h1>
  <p><?= $e($text) ?></p>
  <?php if ($links): ?><div class="links"><?php foreach ($links as [$href, $label]): ?><a href="<?= $e($href) ?>"<?= str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><?= $e($label) ?></a><?php endforeach; ?></div><?php endif; ?>
</main>
</body>
</html>
<?php
    exit;
}
