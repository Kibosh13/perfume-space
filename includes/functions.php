<?php
/**
 * Perfume Space — Core helper functions & data layer (No CMS, pure PHP)
 */

define('DATA_DIR', __DIR__ . '/../data');
define('CONFIG_FILE', DATA_DIR . '/site_config.json');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
define('ORDERS_FILE', DATA_DIR . '/orders.json');

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
        '/women'    => '/?gender=Женские#catalog',
        '/men'      => '/?gender=Мужские#catalog',
        '/unisex'   => '/?gender=Унисекс#catalog',
        '/brands'   => '/#brands',
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
        'total'           => $o['total'] ?? 0,
        'gift_2ml'        => !empty($o['gift_2ml']),
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
        $lines[] = "Итого к оплате: " . ($entry['total'] ?? 0) . " ₽";
        if (!empty($entry['gift_2ml'])) {
            $lines[] = "Подарок: Парфюм 2 мл (заказ от 8 000 ₽)";
        }
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
    return (int)round($base * (100 - $pct) / 100);
}
