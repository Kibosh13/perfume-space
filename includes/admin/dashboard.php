<?php
/** Сводка: ключевые цифры, свежие заказы, что требует внимания */
$real = array_filter($orders, fn($o) => ($o['type'] ?? 'order') !== 'request');
$requests = array_filter($orders, fn($o) => ($o['type'] ?? '') === 'request');
$by_status = array_fill_keys(array_keys(ORDER_STATUSES), 0);
foreach ($real as $o) $by_status[order_status($o)]++;
$parse = fn($o) => DateTime::createFromFormat('d.m.Y H:i', (string)($o['created_at'] ?? '')) ?: null;
$today = date('Y-m-d');
$month = date('Y-m');
$today_cnt = 0; $month_sum = 0; $month_cnt = 0;
foreach ($real as $o) {
    $d = $parse($o);
    if (!$d) continue;
    if ($d->format('Y-m-d') === $today) $today_cnt++;
    if ($d->format('Y-m') === $month && order_status($o) !== 'cancelled') { $month_sum += (int)($o['total'] ?? 0); $month_cnt++; }
}
$new_requests = count(array_filter($requests, fn($o) => order_status($o) === 'new'));
$pending_reviews = count(array_filter($reviews, fn($r) => ($r['status'] ?? '') === 'pending'));
$in_stock = count(array_filter($products, fn($p) => !empty($p['in_stock'])));
$no_desc = count(array_filter($products, fn($p) => empty($p['accords']) && empty($p['description'])));
$no_photo = count(array_filter($products, fn($p) => empty($p['image'])));
$tbd = 0;
array_walk_recursive($config, function ($v) use (&$tbd) { if (is_string($v) && str_contains($v, '[заполнить')) $tbd++; });
foreach (info_pages() as $pg) $tbd += substr_count((string)($pg['body'] ?? ''), '[заполнить');
$recent = array_slice($orders, 0, 8);
?>
<div class="adm-page-head">
  <h1>Сводка</h1>
  <a class="btn btn--line btn--sm" href="/" target="_blank">Открыть сайт</a>
</div>

<div class="kpis">
  <a class="kpi kpi--accent" href="/admin.php?tab=orders&status=new"><span>Новые заказы</span><b><?= $by_status['new'] ?></b><small>ждут подтверждения</small></a>
  <a class="kpi" href="/admin.php?tab=orders&type=request"><span>Новые заявки</span><b><?= $new_requests ?></b><small>бронь и вопросы</small></a>
  <div class="kpi"><span>Заказов сегодня</span><b><?= $today_cnt ?></b><small><?= date('d.m.Y') ?></small></div>
  <div class="kpi"><span>Выручка за месяц</span><b><?= format_price($month_sum) ?></b><small><?= $month_cnt ?> <?= plural_ru($month_cnt, 'заказ', 'заказа', 'заказов') ?><?= $month_cnt ? ', средний чек ' . format_price((int)round($month_sum / $month_cnt)) : '' ?></small></div>
</div>

<div class="adm-grid">
  <section class="admin-card">
    <div class="adm-card-head"><h2>Последние заказы и заявки</h2><a href="/admin.php?tab=orders" class="link-arrow">Все заказы</a></div>
    <?php if (!$recent): ?><p class="muted">Заказов пока нет. Они появятся здесь, как только покупатели оформят первый заказ.</p><?php endif; ?>
    <table class="admin-table">
      <?php foreach ($recent as $o): $st = order_status($o); ?>
        <tr class="row-link" onclick="location='/admin.php?tab=orders&id=<?= urlencode($o['id']) ?>'">
          <td><b><?= h($o['id']) ?></b><div class="muted small"><?= h($o['created_at'] ?? '') ?></div></td>
          <td><?= h($o['customer']['name'] ?? '') ?><div class="muted small"><?= h($o['customer']['phone'] ?? '') ?></div></td>
          <td><?= ($o['type'] ?? '') === 'request' ? '<span class="muted">' . h(mb_substr((string)($o['comment'] ?? ''), 0, 60)) . '</span>' : format_price((int)($o['total'] ?? 0)) ?></td>
          <td><span class="st st--<?= $st ?>"><?= ORDER_STATUSES[$st] ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </section>

  <section class="admin-card">
    <div class="adm-card-head"><h2>Требует внимания</h2></div>
    <ul class="todo">
      <?php if (empty($config['admin_password_hash'])): ?><li class="todo--warn"><a href="/admin.php?tab=settings&sub=access">Сменить стандартный пароль админки</a></li><?php endif; ?>
      <?php if ($pending_reviews): ?><li><a href="/admin.php?tab=reviews">Отзывы на модерации: <?= $pending_reviews ?></a></li><?php endif; ?>
      <?php if ($tbd): ?><li><a href="/admin.php?tab=settings&sub=contacts">Незаполненных полей «[заполнить]»: <?= $tbd ?></a> — реквизиты, документы, тексты страниц</li><?php endif; ?>
      <?php if ($no_desc): ?><li><a href="/admin.php?tab=products">Ароматов без нот и описания: <?= $no_desc ?></a> — заполните через карточку или Excel</li><?php endif; ?>
      <?php if ($no_photo): ?><li><a href="/admin.php?tab=products">Ароматов без фото: <?= $no_photo ?></a></li><?php endif; ?>
      <?php if (!is_filled($config['integrations']['order_email'] ?? '') && !is_filled($config['integrations']['telegram_bot_token'] ?? '')): ?><li class="todo--warn"><a href="/admin.php?tab=settings&sub=access">Настроить уведомления о заказах на почту или в Telegram</a></li><?php endif; ?>
      <?php if (!is_filled($config['integrations']['yandex_metrika_id'] ?? '')): ?><li><a href="/admin.php?tab=settings&sub=access">Подключить Яндекс Метрику</a></li><?php endif; ?>
    </ul>
    <div class="mini-stats">
      <div><b><?= count($products) ?></b><span>ароматов</span></div>
      <div><b><?= $in_stock ?></b><span>в наличии</span></div>
      <div><b><?= count(brand_list()) ?></b><span>брендов</span></div>
      <div><b><?= count(collections()) ?></b><span>подборок</span></div>
      <div><b><?= count(articles(false)) ?></b><span>статей</span></div>
      <div><b><?= count(array_filter($reviews, fn($r) => ($r['status'] ?? '') === 'published')) ?></b><span>отзывов</span></div>
    </div>
  </section>
</div>
