<?php
/**
 * Perfume Space — JSON API: заказ, бронь, заявка, статусы своих заказов
 */
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/includes/functions.php';

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }
    return $digits;
}

function validate_contacts(array $input): array {
    $name = trim((string)($input['name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    if ($name === '' || $phone === '' || $email === '') {
        respond(['ok' => false, 'error' => 'Заполните имя, телефон и почту.'], 400);
    }
    if (strlen(normalize_phone($phone)) < 10) {
        respond(['ok' => false, 'error' => 'Проверьте номер телефона.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['ok' => false, 'error' => 'Проверьте адрес почты.'], 400);
    }
    if (empty($input['consent'])) {
        respond(['ok' => false, 'error' => 'Нужно согласие на обработку персональных данных.'], 400);
    }
    return [mb_substr($name, 0, 80), mb_substr($phone, 0, 30), mb_substr($email, 0, 120)];
}

function next_id(array $orders, string $prefix): string {
    return $prefix . '-' . date('ymd') . '-' . str_pad((string)(count($orders) + 1), 4, '0', STR_PAD_LEFT);
}

$config = get_site_config();
$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : $_POST;
if (!is_array($input)) {
    $input = [];
}
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if ($action === 'create_order') {
    [$name, $phone, $email] = validate_contacts($input);
    $mode = ($input['order_mode'] ?? 'buy') === 'book' ? 'book' : 'buy';
    $delivery = mb_substr(trim((string)($input['delivery'] ?? '')), 0, 80);
    $payment = mb_substr(trim((string)($input['payment'] ?? '')), 0, 80);
    $address = mb_substr(trim((string)($input['address'] ?? '')), 0, 300);
    $items = is_array($input['items'] ?? null) ? $input['items'] : [];
    if (!$items) {
        respond(['ok' => false, 'error' => 'Корзина пуста.'], 400);
    }

    $catalog = [];
    foreach (get_products() as $p) {
        $catalog[(int)$p['id']] = $p;
    }

    $clean = [];
    $subtotal = 0;
    $qty_total = 0;
    foreach (array_slice($items, 0, 50) as $it) {
        $p = $catalog[(int)($it['id'] ?? 0)] ?? null;
        $vol = (string)($it['volume'] ?? '');
        if (!$p || empty($p['in_stock']) || empty($p['volumes'][$vol])) {
            continue;
        }
        $qty = max(1, min(20, (int)($it['qty'] ?? 1)));
        $price = product_price($p, $vol);
        $subtotal += $price * $qty;
        $qty_total += $qty;
        $clean[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'volume' => $vol, 'price' => $price, 'qty' => $qty];
    }
    if (!$clean) {
        respond(['ok' => false, 'error' => 'Товары из корзины сейчас недоступны.'], 400);
    }

    $bundle_min = (int)($config['promos']['bundle_min_items'] ?? 5);
    $bundle_pct = (int)($config['promos']['bundle_discount_percent'] ?? 10);
    $gift_from = (int)($config['promos']['gift_threshold'] ?? 8000);
    $discount = $qty_total >= $bundle_min ? (int)round($subtotal * $bundle_pct / 100) : 0;
    $total = $subtotal - $discount;

    $orders = get_orders();
    $order = [
        'id'              => next_id($orders, $mode === 'book' ? 'BR' : 'PS'),
        'type'            => $mode === 'book' ? 'booking' : 'order',
        'status'          => 'active',
        'created_at'      => date('d.m.Y H:i'),
        'customer'        => ['name' => $name, 'phone' => $phone, 'email' => $email],
        'delivery'        => $delivery,
        'payment'         => $payment,
        'address'         => $address,
        'items'           => $clean,
        'total_qty'       => $qty_total,
        'subtotal'        => $subtotal,
        'discount_bundle' => $discount,
        'total'           => $total,
        'gift_2ml'        => $total >= $gift_from,
        'comment'         => '',
    ];
    array_unshift($orders, $order);
    save_orders($orders);
    send_lead_notifications($order, $config);

    respond([
        'ok'      => true,
        'order'   => public_order($order),
        'message' => $mode === 'book'
            ? "Бронь {$order['id']} оформлена. Менеджер свяжется с вами для подтверждения."
            : "Заказ {$order['id']} принят. Менеджер подтвердит его и пришлёт ссылку на оплату.",
    ]);
}

if ($action === 'create_request') {
    [$name, $phone, $email] = validate_contacts($input);
    $type = mb_substr(trim((string)($input['request_type'] ?? 'Заявка')), 0, 80);
    $perfume = mb_substr(trim((string)($input['perfume_name'] ?? '')), 0, 160);

    $orders = get_orders();
    $req = [
        'id'         => next_id($orders, 'RQ'),
        'type'       => 'request',
        'status'     => 'active',
        'created_at' => date('d.m.Y H:i'),
        'customer'   => ['name' => $name, 'phone' => $phone, 'email' => $email],
        'delivery'   => '',
        'payment'    => '',
        'address'    => '',
        'items'      => [],
        'total'      => 0,
        'gift_2ml'   => false,
        'comment'    => $type . ($perfume !== '' ? " · {$perfume}" : ''),
    ];
    array_unshift($orders, $req);
    save_orders($orders);
    send_lead_notifications($req, $config);

    respond(['ok' => true, 'order' => public_order($req), 'message' => "Заявка {$req['id']} отправлена. Мы свяжемся с вами в ближайшее время."]);
}

if ($action === 'my_orders') {
    $phone = normalize_phone((string)($input['phone'] ?? ''));
    $ids = array_slice(array_map('strval', (array)($input['ids'] ?? [])), 0, 50);
    if (strlen($phone) < 10 || !$ids) {
        respond(['ok' => true, 'orders' => []]);
    }
    $found = [];
    foreach (get_orders() as $o) {
        if (in_array($o['id'] ?? '', $ids, true) && normalize_phone($o['customer']['phone'] ?? '') === $phone) {
            $found[] = public_order($o);
        }
    }
    respond(['ok' => true, 'orders' => $found]);
}

respond(['ok' => false, 'error' => 'Unknown action'], 400);
