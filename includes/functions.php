<?php
/**
 * Perfume Space — Core helper functions & data layer (No CMS, pure PHP)
 */

define('DATA_DIR', __DIR__ . '/../data');
define('CONFIG_FILE', DATA_DIR . '/site_config.json');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
define('ORDERS_FILE', DATA_DIR . '/orders.json');
define('REVIEWS_FILE', DATA_DIR . '/reviews.json');
define('BRANDS_FILE', DATA_DIR . '/brands.json');
define('COLLECTIONS_FILE', DATA_DIR . '/collections.json');
define('PAGES_FILE', DATA_DIR . '/pages.json');
define('VOTES_FILE', DATA_DIR . '/similar_votes.json');
define('UPLOADS_DIR', __DIR__ . '/../assets/img/uploads');
define('UPLOADS_URL', '/assets/img/uploads');

/**
 * 301 Redirects from old perfume-space.ru (Tilda) addresses
 */
function handle_legacy_redirects(): void {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri_clean = rtrim($uri, '/');
    if ($uri_clean === '') {
        $uri_clean = '/';
    }

    $redirects = [
        '/women'    => '/category/women_parfum/',
        '/men'      => '/category/men_parfum/',
        '/unisex'   => '/category/unisex/',
        '/privacy.php' => '/privacy/',
        '/discount' => '/#promo',
        '/present'  => '/#promo',
        '/reviews'  => '/#reviews',
        '/catalog'  => '/#catalog',
        '/index.html' => '/',
    ];

    if (isset($redirects[$uri_clean])) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirects[$uri_clean]);
        exit;
    }
}

function get_site_config(): array {
    if (!file_exists(CONFIG_FILE)) {
        return [];
    }
    $json = file_get_contents(CONFIG_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_site_config(array $config): bool {
    return file_put_contents(
        CONFIG_FILE,
        json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

function get_products(): array {
    if (!file_exists(PRODUCTS_FILE)) {
        return [];
    }
    $json = file_get_contents(PRODUCTS_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function save_products(array $products): bool {
    return file_put_contents(
        PRODUCTS_FILE,
        json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

function get_orders(): array {
    if (!file_exists(ORDERS_FILE)) {
        return [];
    }
    $json = file_get_contents(ORDERS_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function get_reviews(): array {
    if (!file_exists(REVIEWS_FILE)) {
        return [];
    }
    $data = json_decode((string)file_get_contents(REVIEWS_FILE), true);
    return is_array($data) ? $data : [];
}

function save_reviews(array $reviews): bool {
    return file_put_contents(
        REVIEWS_FILE,
        json_encode(array_values($reviews), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

function save_orders(array $orders): bool {
    return file_put_contents(
        ORDERS_FILE,
        json_encode(array_values($orders), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) !== false;
}

function public_order(array $o): array {
    return [
        'id'              => $o['id'] ?? '',
        'type'            => $o['type'] ?? 'order',
        'status'          => $o['status'] ?? 'active',
        'created_at'      => $o['created_at'] ?? '',
        'delivery'        => $o['delivery'] ?? '',
        'payment'         => $o['payment'] ?? '',
        'items'           => $o['items'] ?? [],
        'subtotal'        => $o['subtotal'] ?? 0,
        'discount_bundle' => $o['discount_bundle'] ?? 0,
        'promo_code'      => $o['promo_code'] ?? '',
        'discount_promo'  => $o['discount_promo'] ?? 0,
        'total'           => $o['total'] ?? 0,
        'gift_2ml'        => !empty($o['gift_2ml']),
        'gift_name'       => $o['gift_name'] ?? '',
        'comment'         => $o['comment'] ?? '',
    ];
}

function is_filled(?string $value): bool {
    $value = trim((string)$value);
    return $value !== '' && !str_contains($value, '[заполнить');
}

function h(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_price(int|float $num): string {
    return number_format((float)$num, 0, '.', ' ') . ' ₽';
}

/**
 * Send order / request notification to Email & Telegram Bot if configured
 */
function send_lead_notifications(array $entry, array $config): void {
    $to = $config['integrations']['order_email'] ?? '';
    $subject = 'Perfume Space — Новая заявка / заказ #' . ($entry['id'] ?? '');
    $lines = [
        "Номер: " . ($entry['id'] ?? ''),
        "Тип: " . ($entry['type'] ?? 'order'),
        "Дата: " . ($entry['created_at'] ?? date('d.m.Y H:i')),
        "Имя: " . ($entry['customer']['name'] ?? ''),
        "Телефон: " . ($entry['customer']['phone'] ?? ''),
        "Почта: " . ($entry['customer']['email'] ?? ''),
    ];
    if (!empty($entry['items'])) {
        $lines[] = "\nСостав заказа:";
        foreach ($entry['items'] as $it) {
            $lines[] = "- {$it['name']} ({$it['volume']} мл) × {$it['qty']} = " . ($it['price'] * $it['qty']) . " ₽";
        }
        if (!empty($entry['discount_bundle'])) $lines[] = "Скидка за количество: −" . $entry['discount_bundle'] . " ₽";
        if (!empty($entry['promo_code'])) $lines[] = "Промокод {$entry['promo_code']}: −" . ($entry['discount_promo'] ?? 0) . " ₽";
        if (!empty($entry['points_spend'])) $lines[] = "Баллы: −" . $entry['points_spend'] . " ₽";
        $lines[] = "Итого к оплате: " . ($entry['total'] ?? 0) . " ₽ (доставка бесплатно)";
        if (!empty($entry['gift_2ml'])) {
            $lines[] = "Подарок: пробник 2 мл" . (!empty($entry['gift_name']) ? " — {$entry['gift_name']}" : '');
        }
        $lines[] = "Доставка: " . ($entry['delivery'] ?? '') . ", " . ($entry['address'] ?? '');
        $lines[] = "Оплата: " . ($entry['payment'] ?? '');
    }
    if (!empty($entry['comment'])) {
        $lines[] = "Комментарий: " . $entry['comment'];
    }
    $body = implode("\n", $lines);

    // Non-blocking mail attempt if mail() is configured on hosting
    if ($to && !str_contains($to, '[заполнить')) {
        @mail($to, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\nFrom: noreply@perfume-space.ru");
    }

    // Telegram Bot notification if token & chat_id are filled in Admin
    $bot_token = $config['integrations']['telegram_bot_token'] ?? '';
    $chat_id = $config['integrations']['telegram_chat_id'] ?? '';
    if ($bot_token && $chat_id && !str_contains($bot_token, '[заполнить') && !str_contains($chat_id, '[заполнить')) {
        $tg_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query(['chat_id' => $chat_id, 'text' => $body]),
                'timeout' => 2
            ]
        ]);
        @file_get_contents($tg_url, false, $ctx);
    }
}

function plural_ru(int $n, string $one, string $few, string $many): string
{
    $m10 = $n % 10;
    $m100 = $n % 100;
    if ($m10 === 1 && $m100 !== 11) return $one;
    if ($m10 >= 2 && $m10 <= 4 && ($m100 < 10 || $m100 >= 20)) return $few;
    return $many;
}

function product_price(array $p, string $vol): int
{
    $base = (int)($p['volumes'][$vol] ?? 0);
    $pct = max(0, min(80, (int)($p['discount'] ?? 0)));
    $price = (int)round($base * (100 - $pct) / 100);
    $volume_off = ['10' => 3, '15' => 7];
    $off = $volume_off[$vol] ?? 0;
    return (int)round($price * (100 - $off) / 100);
}


function json_load(string $file, $default = []) {
    if (!is_file($file)) {
        return $default;
    }
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function json_save(string $file, $data): bool {
    return file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

/** Латинский slug для адресов: «Tom Ford» → tom-ford */
function slugify(string $s): string {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, $map);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s));
    return trim((string)$s, '-') ?: 'item';
}

/** Простая разметка для текстов из админки: абзацы, «## заголовок», «- список», **жирный**, [текст](ссылка) */
function render_text(string $text, array $vars = []): string {
    if ($vars) {
        $text = preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => array_key_exists($m[1], $vars) ? (string)$vars[$m[1]] : $m[0], $text);
    }
    $inline = function (string $line): string {
        $line = h($line);
        $line = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $line);
        $line = preg_replace('/\[([^\]]+)\]\(((?:https?:\/\/|\/|mailto:|#)[^)\s]*)\)/u', '<a href="$2">$1</a>', $line);
        $line = preg_replace('/\[(заполнить[^\]]*)\]/u', '<span class="tbd">[$1]</span>', $line);
        return $line;
    };
    $out = [];
    $list = false;
    $para = [];
    $flush = function () use (&$para, &$out, $inline) {
        if ($para) {
            $out[] = '<p>' . implode('<br>', array_map($inline, $para)) . '</p>';
            $para = [];
        }
    };
    foreach (preg_split('/\r?\n/', $text) as $raw) {
        $line = rtrim($raw);
        if (preg_match('/^\s*[-•]\s+(.*)$/u', $line, $m)) {
            $flush();
            if (!$list) { $out[] = '<ul>'; $list = true; }
            $out[] = '<li>' . $inline($m[1]) . '</li>';
            continue;
        }
        if ($list) { $out[] = '</ul>'; $list = false; }
        if (preg_match('/^(#{2,3})\s+(.*)$/u', $line, $m)) {
            $flush();
            $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
            $out[] = "<{$tag}>" . $inline($m[2]) . "</{$tag}>";
            continue;
        }
        if (trim($line) === '') { $flush(); continue; }
        $para[] = $line;
    }
    $flush();
    if ($list) $out[] = '</ul>';
    return implode("\n", $out);
}

/** Загрузка картинки/документа из админки. Возвращает публичный путь или '' */
function save_upload(array $file, array $allowed = ['jpg', 'jpeg', 'png', 'webp']): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return '';
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        return '';
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return '';
    }
    $mime = function_exists('mime_content_type') ? (string)mime_content_type($file['tmp_name']) : '';
    $ok_mime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if ($mime !== '' && !in_array($mime, $ok_mime, true)) {
        return '';
    }
    if (!is_dir(UPLOADS_DIR)) {
        @mkdir(UPLOADS_DIR, 0775, true);
    }
    $name = date('ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '-' . substr(slugify(pathinfo((string)$file['name'], PATHINFO_FILENAME)), 0, 40) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . '/' . $name)) {
        return '';
    }
    return UPLOADS_URL . '/' . $name;
}


/* ---------- Промокоды ---------- */

function promo_value(array $p): array {
    $type = ($p['type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
    $value = (int)($p['value'] ?? ($p['percent'] ?? 0));
    return [$type, $value];
}

function find_promo(string $code): ?array {
    $code = mb_strtoupper(trim($code));
    if ($code === '') return null;
    foreach (get_site_config()['promo_codes'] ?? [] as $p) {
        if (mb_strtoupper((string)($p['code'] ?? '')) === $code) return $p;
    }
    return null;
}

/** Проверка промокода. Возвращает текст ошибки или '' */
function promo_error(?array $p, int $subtotal, string $phone = '', string $email = ''): string {
    if (!$p || empty($p['active'])) return 'Такого промокода нет или он больше не действует';
    if (!empty($p['expires']) && strtotime($p['expires'] . ' 23:59:59') < time()) return 'Срок действия промокода истёк';
    if (!empty($p['max_uses']) && (int)($p['used'] ?? 0) >= (int)$p['max_uses']) return 'Промокод больше не действует';
    if (!empty($p['min_sum']) && $subtotal < (int)$p['min_sum']) return 'Промокод действует при заказе от ' . format_price((int)$p['min_sum']);
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
    $email = mb_strtolower(trim($email));
    if ((!empty($p['once']) || !empty($p['first_order'])) && ($digits !== '' || $email !== '')) {
        foreach (get_orders() as $o) {
            if (($o['type'] ?? '') === 'request') continue;
            $od = preg_replace('/\D+/', '', (string)($o['customer']['phone'] ?? ''));
            if (strlen($od) === 11 && $od[0] === '8') $od = '7' . substr($od, 1);
            $same = ($digits !== '' && $od === $digits) || ($email !== '' && mb_strtolower((string)($o['customer']['email'] ?? '')) === $email);
            if (!$same) continue;
            if (!empty($p['first_order'])) return 'Промокод действует только на первый заказ';
            if (mb_strtoupper((string)($o['promo_code'] ?? '')) === mb_strtoupper((string)$p['code'])) return 'Этот промокод уже использован';
        }
    }
    return '';
}

function promo_discount(array $p, int $subtotal): int {
    [$type, $value] = promo_value($p);
    $d = $type === 'amount' ? $value : (int)round($subtotal * $value / 100);
    return max(0, min($d, $subtotal));
}

function promo_public(array $p): array {
    [$type, $value] = promo_value($p);
    return ['code' => mb_strtoupper((string)$p['code']), 'type' => $type, 'value' => $value, 'min_sum' => (int)($p['min_sum'] ?? 0), 'title' => (string)($p['title'] ?? '')];
}
