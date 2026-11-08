<?php
/** @var array $info */
$site = site_url();
$config = get_site_config();
$legal = $config['legal'] ?? [];
$c = $config['contacts'] ?? [];
$promos = $config['promos'] ?? [];
$fill = fn($v, $label) => is_filled($v ?? '') ? (string)$v : "[заполнить: {$label}]";
$vars = [
    'entity' => $fill($legal['entity'] ?? '', 'наименование ИП'),
    'inn' => $fill($legal['inn'] ?? '', 'ИНН'),
    'ogrnip' => $fill($legal['ogrnip'] ?? '', 'ОГРНИП'),
    'address' => $fill($legal['address'] ?? '', 'адрес'),
    'declaration' => $fill($legal['declaration_number'] ?? '', 'номер декларации'),
    'email' => $fill($c['email'] ?? '', 'почта'),
    'phone' => $fill($c['phone'] ?? '', 'телефон'),
    'site' => $site,
    'work_hours' => $config['work_hours'] ?? '',
    'gift_from' => format_price((int)($promos['gift_threshold'] ?? 8000)),
    'bundle_min' => (int)($promos['bundle_min_items'] ?? 5),
    'bundle_pct' => (int)($promos['bundle_discount_percent'] ?? 10),
    'years' => '2018',
];
$url = '/' . $info['slug'] . '/';
$docs = array_values(array_filter((array)($info['documents'] ?? []), fn($d) => !empty($d['file'])));
layout_start([
    'page' => 'info',
    'title' => ($info['meta_title'] ?? '') ?: $info['title'] . ' — Perfume Space',
    'description' => $info['meta_description'] ?? '',
    'canonical' => $url,
]);
$siblings = array_values(array_filter(info_pages(), fn($p) => ($p['group'] ?? '') === ($info['group'] ?? '')));
?>
<main class="page page--info">
  <div class="container info-layout">
    <div>
      <?= breadcrumbs_html([[$info['title'], '']], $site) ?>
      <article class="prose">
        <div class="eyebrow" style="margin-bottom:18px"><?= h($info['group'] ?? '') ?></div>
        <h1 class="h-section"><?= h($info['title']) ?></h1>
        <?php if (!empty($info['lead'])): ?><p class="lead"><?= h($info['lead']) ?></p><?php endif; ?>
        <?= render_text((string)($info['body'] ?? ''), $vars) ?>
        <?php if ($docs): ?>
          <h2>Документы</h2>
          <div class="docs-grid">
            <?php foreach ($docs as $d): $is_pdf = str_ends_with(strtolower($d['file']), '.pdf'); ?>
              <a class="doc-file" href="<?= h($d['file']) ?>" target="_blank" rel="noopener">
                <?php if ($is_pdf): ?><svg class="icon"><use href="#i-doc"/></svg><?php else: ?><img src="<?= h($d['file']) ?>" alt="<?= h($d['title'] ?? '') ?>" loading="lazy"><?php endif; ?>
                <span><?= h($d['title'] ?? 'Документ') ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    </div>
    <?php if (count($siblings) > 1): ?>
    <aside class="info-nav">
      <div class="f-title"><?= h($info['group'] ?? '') ?></div>
      <?php foreach ($siblings as $s): ?><a href="/<?= h($s['slug']) ?>/" class="<?= $s['slug'] === $info['slug'] ? 'is-active' : '' ?>"><?= h($s['title']) ?></a><?php endforeach; ?>
    </aside>
    <?php endif; ?>
  </div>
</main>
<?php layout_end(['page' => 'info']);
