<?php
header('Content-Type: application/xml; charset=UTF-8');
$site = site_url();
$today = date('Y-m-d');
$urls = [['/', '1.0', 'daily'], ['/brands/', '0.8', 'weekly'], ['/category/', '0.8', 'weekly']];
foreach (collections() as $c) $urls[] = [collection_url($c), '0.8', 'daily'];
foreach (array_keys(brand_list()) as $b) $urls[] = [brand_url($b), '0.7', 'weekly'];
foreach (catalog() as $p) $urls[] = [product_url($p), '0.6', 'weekly'];
foreach (info_pages() as $p) $urls[] = ['/' . $p['slug'] . '/', '0.4', 'monthly'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$u, $prio, $freq]) {
    echo '  <url><loc>' . htmlspecialchars($site . $u, ENT_XML1) . "</loc><lastmod>{$today}</lastmod><changefreq>{$freq}</changefreq><priority>{$prio}</priority></url>\n";
}
echo '</urlset>';
