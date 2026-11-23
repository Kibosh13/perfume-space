<?php
/** Заказы и заявки: список с фильтрами и карточка заказа */
$norm_phone = function (string $p): string {
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) === 11 && $d[0] === '8') $d = '7' . substr($d, 1);
    return $d;
};
$type_names = ['order' => 'Заказ', 'booking' => 'Бронь', 'request' => 'Заявка'];
$oid = (string)($_GET['id'] ?? '');
$o = null;
foreach ($orders as $x) if (($x['id'] ?? '') === $oid) $o = $x;

if ($o):
    $st = order_status($o);
    $phone = (string)($o['customer']['phone'] ?? '');
    $digits = $norm_phone($phone);
    $others = array_values(array_filter($orders, fn($x) => ($x['id'] ?? '') !== $oid && $digits !== '' && $norm_phone((string)($x['customer']['phone'] ?? '')) === $digits));
?>
<div class="adm-page-head">
  <div><a href="/admin.php?tab=orders" class="back">← Все заказы</a><h1><?= $type_names[$o['type'] ?? 'order'] ?? 'Заказ' ?> <?= h($o['id']) ?></h1><div class="muted"><?= h($o['created_at'] ?? '') ?></div></div>
  <span class="st st--<?= $st ?> st--lg"><?= ORDER_STATUSES[$st] ?></span>
</div>

<div class="adm-grid adm-grid--order">
  <div>
    <section class="admin-card">
      <h2>Состав</h2>
      <?php if (!empty($o['items'])): ?>
        <table class="admin-table">
          <thead><tr><th>Аромат</th><th>Объём</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr></thead>
          <?php foreach ($o['items'] as $it): $pp = catalog()[(int)($it['id'] ?? 0)] ?? null; ?>
            <tr>
              <td><?php if ($pp): ?><a href="<?= h(product_url($pp)) ?>" target="_blank"><?= h($it['name']) ?></a><?php else: ?><?= h($it['name'] ?? '') ?><?php endif; ?></td>
              <td><?= h($it['volume'] ?? '') ?> мл</td><td><?= format_price((int)($it['price'] ?? 0)) ?></td><td><?= (int)($it['qty'] ?? 1) ?></td><td><b><?= format_price((int)($it['price'] ?? 0) * (int)($it['qty'] ?? 1)) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <div class="sum-lines">
          <div><span>Товары</span><b><?= format_price((int)($o['subtotal'] ?? 0)) ?></b></div>
          <?php if (!empty($o['discount_bundle'])): ?><div><span>Скидка за количество</span><b>−<?= format_price((int)$o['discount_bundle']) ?></b></div><?php endif; ?>
          <?php if (!empty($o['promo_code'])): ?><div><span>Промокод <?= h($o['promo_code']) ?></span><b>−<?= format_price((int)($o['discount_promo'] ?? 0)) ?></b></div><?php endif; ?>
          <?php if (!empty($o['points_spend'])): ?><div><span>Баллы</span><b>−<?= format_price((int)$o['points_spend']) ?></b></div><?php endif; ?>
          <div><span>Доставка</span><b>бесплатно</b></div>
          <div class="sum-lines__total"><span>Итого к оплате</span><b><?= format_price((int)($o['total'] ?? 0)) ?></b></div>
        </div>
        <?php if (!empty($o['gift_2ml'])): ?><p class="gift-note">🎁 Пробник 2 мл × <?= max(1, (int)($o['gift_count'] ?? 1)) ?><?= !empty($o['gift_name']) ? ': ' . h($o['gift_name']) : '' ?></p><?php endif; ?>
      <?php else: ?>
        <p><?= h($o['comment'] ?? '') ?></p>
      <?php endif; ?>
      <?php if (!empty($o['comment']) && !empty($o['items'])): ?><p class="client-note"><b>Комментарий покупателя:</b> <?= h($o['comment']) ?></p><?php endif; ?>
    </section>

    <?php if (!empty($o['history'])): ?>
    <section class="admin-card">
      <h2>История</h2>
      <ul class="history"><?php foreach (array_reverse($o['history']) as $hst): ?><li><span><?= h($hst['at']) ?></span><?= h($hst['text']) ?></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>

    <?php if ($others): ?>
    <section class="admin-card">
      <h2>Другие заказы этого покупателя</h2>
      <table class="admin-table"><?php foreach ($others as $x): $xs = order_status($x); ?>
        <tr class="row-link" onclick="location='/admin.php?tab=orders&id=<?= urlencode($x['id']) ?>'"><td><b><?= h($x['id']) ?></b></td><td><?= h($x['created_at'] ?? '') ?></td><td><?= format_price((int)($x['total'] ?? 0)) ?></td><td><span class="st st--<?= $xs ?>"><?= ORDER_STATUSES[$xs] ?></span></td></tr>
      <?php endforeach; ?></table>
    </section>
    <?php endif; ?>
  </div>

  <div>
    <section class="admin-card">
      <h2>Покупатель</h2>
      <dl class="kv">
        <div><dt>Имя</dt><dd><?= h($o['customer']['name'] ?? '') ?></dd></div>
        <div><dt>Телефон</dt><dd><a href="tel:+<?= h($digits) ?>"><?= h($phone) ?></a><?php if ($digits): ?> · <a href="https://wa.me/<?= h($digits) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd></div>
        <div><dt>Почта</dt><dd><a href="mailto:<?= h($o['customer']['email'] ?? '') ?>"><?= h($o['customer']['email'] ?? '') ?></a></dd></div>
        <?php if (!empty($o['delivery'])): ?><div><dt>Доставка</dt><dd><?= h($o['delivery']) ?></dd></div><?php endif; ?>
        <?php if (!empty($o['address'])): ?><div><dt>Адрес</dt><dd><?= h($o['address']) ?></dd></div><?php endif; ?>
        <?php if (!empty($o['payment'])): ?><div><dt>Оплата</dt><dd><?= h($o['payment']) ?></dd></div><?php endif; ?>
      </dl>
    </section>

    <form method="post" class="admin-card form-grid">
      <h2>Обработка</h2>
      <input type="hidden" name="admin_action" value="update_order">
      <input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
      <div class="form-group"><label>Статус</label>
        <div class="status-pick"><?php foreach (ORDER_STATUSES as $k => $l): ?><label><input type="radio" name="status" value="<?= $k ?>" <?= $k === $st ? 'checked' : '' ?>><span class="st st--<?= $k ?>"><?= $l ?></span></label><?php endforeach; ?></div>
      </div>
      <div class="form-group"><label>Трек-номер</label><input class="form-control" name="track" value="<?= h($o['track'] ?? '') ?>" placeholder="Покупатель увидит его в «Моих заказах»"></div>
      <div class="form-group"><label>Заметка менеджера</label><textarea class="form-control" name="manager_note" rows="4" placeholder="Видно только в админке"><?= h($o['manager_note'] ?? '') ?></textarea></div>
      <button class="btn btn--primary" type="submit">Сохранить</button>
    </form>
    <form method="post" onsubmit="return confirm('Удалить запись <?= h($o['id']) ?> без возможности восстановления?')" style="margin-top:-8px">
      <input type="hidden" name="admin_action" value="delete_order"><input type="hidden" name="order_id" value="<?= h($o['id']) ?>">
      <button class="btn btn--ghost btn--sm" type="submit">Удалить запись (например, тестовую)</button>
    </form>
  </div>
</div>

<?php else:
    $f_status = (string)($_GET['status'] ?? '');
    $f_type = (string)($_GET['type'] ?? '');
    $q = trim((string)($_GET['q'] ?? ''));
    $counts = array_fill_keys(array_keys(ORDER_STATUSES), 0);
    $scope = array_filter($orders, fn($x) => $f_type === 'request' ? ($x['type'] ?? '') === 'request' : ($f_type === '' ? ($x['type'] ?? 'order') !== 'request' : ($x['type'] ?? 'order') === $f_type));
    foreach ($scope as $x) $counts[order_status($x)]++;
    $list = array_values(array_filter($scope, function ($x) use ($f_status, $q, $norm_phone) {
        if ($f_status !== '' && order_status($x) !== $f_status) return false;
        if ($q !== '') {
            $hay = mb_strtolower(implode(' ', [$x['id'] ?? '', $x['customer']['name'] ?? '', $x['customer']['email'] ?? '', $x['customer']['phone'] ?? '', $norm_phone((string)($x['customer']['phone'] ?? '')), $x['comment'] ?? '', $x['address'] ?? '']));
            $qq = mb_strtolower($q);
            if (!str_contains($hay, $qq) && !($norm_phone($q) !== '' && str_contains($hay, $norm_phone($q)))) return false;
        }
        return true;
    }));
    $link = fn(array $p) => '/admin.php?' . http_build_query(array_filter(array_merge(['tab' => 'orders', 'type' => $f_type, 'status' => $f_status, 'q' => $q], $p), fn($v) => $v !== ''));
?>
<div class="adm-page-head">
  <h1><?= $f_type === 'request' ? 'Заявки' : 'Заказы' ?></h1>
  <form method="get" class="adm-search">
    <input type="hidden" name="tab" value="orders"><?php if ($f_type): ?><input type="hidden" name="type" value="<?= h($f_type) ?>"><?php endif; ?>
    <input class="form-control" type="search" name="q" value="<?= h($q) ?>" placeholder="Номер, имя, телефон или почта">
    <button class="btn btn--line btn--sm">Найти</button>
  </form>
</div>
<div class="seg-tabs">
  <a href="<?= h($link(['type' => '', 'status' => ''])) ?>" class="<?= $f_type === '' ? 'is-active' : '' ?>">Заказы и брони</a>
  <a href="<?= h($link(['type' => 'order', 'status' => ''])) ?>" class="<?= $f_type === 'order' ? 'is-active' : '' ?>">Только заказы</a>
  <a href="<?= h($link(['type' => 'booking', 'status' => ''])) ?>" class="<?= $f_type === 'booking' ? 'is-active' : '' ?>">Брони</a>
  <a href="<?= h($link(['type' => 'request', 'status' => ''])) ?>" class="<?= $f_type === 'request' ? 'is-active' : '' ?>">Заявки и вопросы</a>
</div>
<div class="status-tabs">
  <a href="<?= h($link(['status' => ''])) ?>" class="<?= $f_status === '' ? 'is-active' : '' ?>">Все <sup><?= array_sum($counts) ?></sup></a>
  <?php foreach (ORDER_STATUSES as $k => $l): ?><a href="<?= h($link(['status' => $k])) ?>" class="<?= $f_status === $k ? 'is-active' : '' ?>"><span class="st st--<?= $k ?>"><?= $l ?></span> <sup><?= $counts[$k] ?></sup></a><?php endforeach; ?>
</div>
<section class="admin-card">
  <?php if (!$list): ?><p class="muted">Ничего не найдено.</p><?php else: ?>
  <div style="overflow-x:auto"><table class="admin-table">
    <thead><tr><th>Номер</th><th>Покупатель</th><th><?= $f_type === 'request' ? 'Запрос' : 'Состав' ?></th><th>Сумма</th><th>Статус</th></tr></thead>
    <?php foreach ($list as $x): $xs = order_status($x); ?>
      <tr class="row-link" onclick="location='/admin.php?tab=orders&id=<?= urlencode($x['id']) ?>'">
        <td><b><?= h($x['id']) ?></b><div class="muted small"><?= h($x['created_at'] ?? '') ?> · <?= $type_names[$x['type'] ?? 'order'] ?? '' ?></div></td>
        <td><?= h($x['customer']['name'] ?? '') ?><div class="muted small"><?= h($x['customer']['phone'] ?? '') ?></div></td>
        <td class="small"><?= !empty($x['items']) ? h(implode(', ', array_map(fn($i) => ($i['name'] ?? '') . ' ' . ($i['volume'] ?? '') . ' мл×' . ($i['qty'] ?? 1), array_slice($x['items'], 0, 3)))) . (count($x['items']) > 3 ? ' …' : '') : h(mb_substr((string)($x['comment'] ?? ''), 0, 90)) ?><?= !empty($x['manager_note']) ? '<div class="muted">📝 ' . h(mb_substr($x['manager_note'], 0, 60)) . '</div>' : '' ?></td>
        <td><?= !empty($x['total']) ? '<b>' . format_price((int)$x['total']) . '</b>' : '—' ?></td>
        <td><span class="st st--<?= $xs ?>"><?= ORDER_STATUSES[$xs] ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
