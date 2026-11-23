<?php
/**
 * Perfume Space — «Библиотека парфюмера»: статьи с встроенными карточками ароматов
 *
 * В тексте статьи (в админке):
 *   ## Подзаголовок            — раздел (попадает в оглавление)
 *   [товары: 846, 594]          — блок карточек ароматов (id или полные названия через запятую)
 *   ![Подпись](/assets/img/…)  — картинка
 */
require_once __DIR__ . '/catalog.php';

define('ARTICLES_FILE', DATA_DIR . '/articles.json');

function articles(bool $only_published = true): array {
    $list = json_load(ARTICLES_FILE, []);
    if ($only_published) {
        $list = array_values(array_filter($list, fn($a) => !empty($a['published'])));
    }
    usort($list, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
    return $list;
}

function article_by_slug(string $slug): ?array {
    foreach (articles() as $a) {
        if (($a['slug'] ?? '') === $slug) return $a;
    }
    return null;
}

function article_url(array $a): string {
    return '/statiy/' . rawurlencode($a['slug']) . '/';
}

function article_categories(): array {
    $cats = [];
    foreach (articles() as $a) {
        $c = trim((string)($a['category'] ?? ''));
        if ($c !== '') $cats[$c] = ($cats[$c] ?? 0) + 1;
    }
    return $cats;
}

function ru_date(string $ymd, bool $with_year = true): string {
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $t = strtotime($ymd);
    if (!$t) return '';
    return (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ($with_year ? ' ' . date('Y', $t) : '');
}

function reading_minutes(string $text): int {
    $words = count(preg_split('/\s+/u', trim(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY));
    return max(1, (int)round($words / 170));
}

/** «846, Tom Ford Lost Cherry» → карточки ароматов */
function shortcode_products(string $list): array {
    $cat = catalog();
    $out = [];
    foreach (preg_split('/\s*[,;]\s*/u', $list) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        if (ctype_digit($part) && isset($cat[(int)$part])) { $out[(int)$part] = $cat[(int)$part]; continue; }
        foreach ($cat as $p) {
            if (mb_strtolower($p['name']) === mb_strtolower($part)) { $out[(int)$p['id']] = $p; continue 2; }
        }
    }
    return array_values($out);
}

function article_products_html(array $products): string {
    if (!$products) return '';
    return '<div class="article-products article-products--' . min(3, count($products)) . '">' . implode('', array_map('card_html', $products)) . '</div>';
}

/** Текст статьи → HTML и оглавление */
function render_article(string $body): array {
    $html = '';
    $toc = [];
    $chunk = [];
    $flush = function () use (&$chunk, &$html) {
        if ($chunk) {
            $html .= render_text(implode("\n", $chunk));
            $chunk = [];
        }
    };
    foreach (preg_split('/\r?\n/', $body) as $line) {
        $t = trim($line);
        if (preg_match('/^\[(?:товары|товар|products?)\s*:\s*(.+)\]$/iu', $t, $m)) {
            $flush();
            $html .= article_products_html(shortcode_products($m[1]));
            continue;
        }
        if (preg_match('/^!\[([^\]]*)\]\(((?:https?:\/\/|\/)[^)\s]+)\)$/u', $t, $m)) {
            $flush();
            $html .= '<figure class="article-figure"><img src="' . h($m[2]) . '" alt="' . h($m[1]) . '" loading="lazy">' . ($m[1] !== '' ? '<figcaption>' . h($m[1]) . '</figcaption>' : '') . '</figure>';
            continue;
        }
        $chunk[] = $line;
    }
    $flush();
    $n = 0;
    $html = preg_replace_callback('/<h2>(.*?)<\/h2>/u', function ($m) use (&$toc, &$n) {
        $id = 's' . (++$n);
        $toc[] = [$id, strip_tags($m[1])];
        return '<h2 id="' . $id . '">' . $m[1] . '</h2>';
    }, $html);
    return [$html, $toc];
}

/** Ароматы, упомянутые в статье (для разметки и блока в конце) */
function article_all_products(array $a): array {
    $ids = [];
    if (preg_match_all('/^\s*\[(?:товары|товар|products?)\s*:\s*(.+)\]\s*$/imu', (string)($a['body'] ?? ''), $mm)) {
        foreach ($mm[1] as $list) foreach (shortcode_products($list) as $p) $ids[(int)$p['id']] = $p;
    }
    foreach (shortcode_products(implode(',', (array)($a['products'] ?? []))) as $p) $ids[(int)$p['id']] = $p;
    return array_values($ids);
}
