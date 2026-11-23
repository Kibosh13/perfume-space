<?php
/** Клиенты: собираются из заказов и заявок по номеру телефона */
$norm = function (string $p): string {
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) === 11 && $d[0] === '8') $d = '7' . substr($d, 1);
    return $d;
};
$clients = [];
foreach ($orders as $o) {
    $key = $norm((string)($o['customer']['phone'] ?? '')) ?: mb_strtolower((string)($o['customer']['email'] ?? ''));
    if ($key === '' || ($o['customer']['name'] ?? '') === 'Вопрос с сайта') continue;
    $c = &$clients[$key];
    $c['name'] = $c['name'] ?? ($o['customer']['name'] ?? '');
    $c['phone'] = $c['phone'] ?? ($o['customer']['phone'] ?? '');
    $c['email'] = $c['email'] ?? ($o['customer']['email'] ?? '');
    $c['orders'] = ($c['orders'] ?? 0) + (($o['type'] ?? 'order') !== 'request' ? 1 : 0);
    $c['requests'] = ($c['requests'] ?? 0) + (($o['type'] ?? '') === 'request' ? 1 : 0);
    if (($o['type'] ?? 'order') !== 'request' && order_status($o) !== 'cancelled') $c['sum'] = ($c['sum'] ?? 0) + (int)($o['total'] ?? 0);
    $c['last'] = $c['last'] ?? ($o['created_at'] ?? '');
    unset($c);
}
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
if ($q !== '') $clients = array_filter($clients, fn($c) => str_contains(mb_strtolower(implode(' ', [$c['name'], $c['phone'], $c['email']])), $q));
uasort($clients, fn($a, $b) => ($b['sum'] ?? 0) <=> ($a['sum'] ?? 0));
?>
<div class="adm-page-head">
  <h1>Клиенты <sup class="muted"><?= count($clients) ?></sup></h1>
  <form method="get" class="adm-search"><input type="hidden" name="tab" value="customers"><input class="form-control" type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Имя, телефон или почта"><button class="btn btn--line btn--sm">Найти</button></form>
</div>
<section class="admin-card">
  <p class="muted" style="margin-bottom:12px">Список собирается автоматически из заказов и заявок. Сортировка — по сумме покупок.</p>
  <?php if (!$clients): ?><p class="muted">Клиентов пока нет.</p><?php else: ?>
  <div style="overflow-x:auto"><table class="admin-table">
    <thead><tr><th>Клиент</th><th>Контакты</th><th>Заказов</th><th>Заявок</th><th>Сумма покупок</th><th>Последнее обращение</th><th></th></tr></thead>
    <?php foreach ($clients as $c): ?>
      <tr>
        <td><b><?= h($c['name']) ?></b></td>
        <td><a href="tel:<?= h($c['phone']) ?>"><?= h($c['phone']) ?></a><div class="small"><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></div></td>
        <td><?= (int)$c['orders'] ?></td><td><?= (int)$c['requests'] ?></td>
        <td><b><?= format_price((int)($c['sum'] ?? 0)) ?></b></td>
        <td class="small"><?= h($c['last']) ?></td>
        <td><a class="btn btn--line btn--sm" href="/admin.php?tab=orders&q=<?= urlencode($c['phone'] ?: $c['email']) ?>">Заказы</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
