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
    $comment = mb_substr(trim((string)($input['comment'] ?? '')), 0, 500);
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

    $promo_code = '';
    $promo_sum = 0;
    if (trim((string)($input['promo_code'] ?? '')) !== '') {
        $promo = find_promo((string)$input['promo_code']);
        $err = promo_error($promo, $subtotal - $discount, $phone, $email);
        if ($err !== '') {
            respond(['ok' => false, 'error' => $err], 400);
        }
        $promo_code = mb_strtoupper((string)$promo['code']);
        $promo_sum = promo_discount($promo, $subtotal - $discount);
    }
    $after = $subtotal - $discount - $promo_sum;
    $loyalty_max = (int)($config['promos']['loyalty_max_share'] ?? 50);
    $points = max(0, min((int)($input['points_spend'] ?? 0), (int)floor($after * $loyalty_max / 100)));
    $total = $after - $points;

    $gift = $catalog[(int)($input['gift_id'] ?? 0)] ?? null;
    $gift_ok = $after >= $gift_from;

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
        'promo_code'      => $promo_code,
        'discount_promo'  => $promo_sum,
        'points_spend'    => $points,
        'total'           => $total,
        'delivery_price'  => 0,
        'gift_2ml'        => $gift_ok,
        'gift_name'       => $gift_ok && $gift ? $gift['name'] : '',
        'comment'         => $comment,
    ];
    array_unshift($orders, $order);
    save_orders($orders);
    if ($promo_code !== '') {
        $cfg = get_site_config();
        foreach ($cfg['promo_codes'] as &$pc) {
            if (mb_strtoupper((string)$pc['code']) === $promo_code) $pc['used'] = (int)($pc['used'] ?? 0) + 1;
        }
        unset($pc);
        save_site_config($cfg);
    }
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

if ($action === 'check_promo') {
    $promo = find_promo((string)($input['code'] ?? ''));
    $err = promo_error($promo, max(0, (int)($input['subtotal'] ?? 0)), (string)($input['phone'] ?? ''), (string)($input['email'] ?? ''));
    if ($err !== '' && !($promo && str_starts_with($err, 'Промокод действует при заказе от'))) {
        respond(['ok' => false, 'error' => $err]);
    }
    respond(['ok' => true, 'promo' => promo_public($promo)]);
}

if ($action === 'vote_similar') {
    $pid = (int)($input['product_id'] ?? 0);
    $sid = (int)($input['similar_id'] ?? 0);
    $ids = array_map(fn($p) => (int)$p['id'], get_products());
    if (!$pid || !$sid || $pid === $sid || !in_array($pid, $ids, true) || !in_array($sid, $ids, true)) {
        respond(['ok' => false, 'error' => 'Выберите аромат из каталога'], 400);
    }
    $votes = json_load(VOTES_FILE, []);
    $votes[(string)$pid][(string)$sid] = (int)($votes[(string)$pid][(string)$sid] ?? 0) + 1;
    json_save(VOTES_FILE, $votes);
    respond(['ok' => true]);
}

if ($action === 'create_review') {
    $name = mb_substr(trim((string)($input['name'] ?? '')), 0, 80);
    $text = mb_substr(trim((string)($input['text'] ?? '')), 0, 1000);
    $perfume = mb_substr(trim((string)($input['perfume'] ?? '')), 0, 160);
    $product_id = (int)($input['product_id'] ?? 0);
    if ($product_id) {
        $found = null;
        foreach (get_products() as $p) {
            if ((int)$p['id'] === $product_id) { $found = $p; break; }
        }
        if (!$found) $product_id = 0; else $perfume = $found['name'];
    }
    $rating = max(1, min(5, (int)($input['rating'] ?? 5)));
    if ($name === '' || mb_strlen($text) < 10) {
        respond(['ok' => false, 'error' => 'Напишите имя и отзыв хотя бы из нескольких слов.'], 400);
    }
    if (empty($input['consent'])) {
        respond(['ok' => false, 'error' => 'Нужно согласие на публикацию отзыва.'], 400);
    }
    $reviews = get_reviews();
    $reviews[] = [
        'id' => 'RV-' . date('ymd') . '-' . str_pad((string)(count($reviews) + 1), 4, '0', STR_PAD_LEFT),
        'author' => $name,
        'product_id' => $product_id,
        'perfume' => $perfume,
        'text' => $text,
        'rating' => $rating,
        'status' => 'pending',
        'source' => 'site',
        'created_at' => date('d.m.Y H:i'),
    ];
    save_reviews($reviews);
    respond(['ok' => true, 'message' => 'Отзыв отправлен на модерацию. На сайте он появится после проверки.']);
}

respond(['ok' => false, 'error' => 'Unknown action'], 400);
