<?php
/** Базовые действия админки: ароматы и отзывы. Ожидает $act, $products, $reviews, $flash. */
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
            'slug'        => slugify($name) . '-' . $new_id,
            'brand'       => $brand,
            'name'        => $name,
            'short_name'  => $name,
            'gender'      => trim((string)($_POST['gender'] ?? 'Унисекс')),
            'family'      => '',
            'notes'       => ['top' => '', 'heart' => '', 'base' => ''],
            'tags'        => ['new'],
            'volumes'     => $vols,
            'old_volumes' => [],
            'min_price'   => !empty($vols) ? min($vols) : 0,
            'in_stock'    => isset($_POST['in_stock']),
            'is_starter'  => false,
            'image'       => '',
            'avito_url'   => '',
            'is_hit'      => isset($_POST['is_hit']),
            'discount'    => max(0, (int)($_POST['discount'] ?? 0))
        ]);
        save_products($products);
        $flash = "Новый аромат «{$name}» добавлен в каталог и отмечен как новинка. Заполните карточку: фото, ноты, описание.";
        header('Location: /admin.php?tab=product&id=' . $new_id);
        exit;
    }
}

// 4. Delete Product
if ($act === 'delete_product') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $products = array_values(array_filter($products, fn($x) => (int)$x['id'] !== $pid));
    save_products($products);
    $flash = "Товар #{$pid} удалён.";
}


if ($act === 'add_review') {
    $text = trim((string)($_POST['text'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    if ($author !== '' && $text !== '') {
        $perfume = mb_substr(trim((string)($_POST['perfume'] ?? '')), 0, 160);
        $rp = $perfume !== '' ? (resolve_ids($perfume, $products)[0] ?? 0) : 0;
        $reviews[] = [
            'id' => 'RV-' . date('ymd-His'),
            'author' => mb_substr($author, 0, 80),
            'product_id' => $rp,
            'perfume' => $perfume,
            'text' => mb_substr($text, 0, 1000),
            'rating' => max(1, min(5, (int)($_POST['rating'] ?? 5))),
            'status' => 'published',
            'source' => 'admin',
            'created_at' => date('d.m.Y H:i'),
        ];
        save_reviews($reviews);
        $flash = 'Отзыв опубликован на сайте.';
    }
}

if ($act === 'moderate_review') {
    $rid = trim((string)($_POST['review_id'] ?? ''));
    $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'rejected';
    foreach ($reviews as &$rev) {
        if (($rev['id'] ?? '') === $rid) {
            $rev['status'] = $status;
            break;
        }
    }
    unset($rev);
    save_reviews($reviews);
    $flash = $status === 'published' ? 'Отзыв опубликован.' : 'Отзыв отклонён.';
}
