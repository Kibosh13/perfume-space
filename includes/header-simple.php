<?php
/** @var string $page_title */
/** @var string $page_description */
/** @var string $page_robots */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($page_title) ?></title>
  <meta name="description" content="<?= h($page_description) ?>">
  <meta name="robots" content="<?= h($page_robots ?? 'index, follow') ?>">
  <?php if (!empty($page_canonical)): ?><link rel="canonical" href="<?= h($page_canonical) ?>"><?php endif; ?>
  <meta name="theme-color" content="#05070c">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=4">
  <?= $page_head_extra ?? '' ?>
</head>
<body>
<header class="header">
  <div class="container header__inner" style="grid-template-columns:auto 1fr">
    <a href="/" class="logo" aria-label="Perfume Space — на главную">
      <img src="/assets/img/logo-white.png" alt="Perfume Space" width="56" height="44">
      <span class="logo__word"><span class="logo__name">Perfume<br>Space</span></span>
    </a>
    <div style="display:flex;justify-content:flex-end;gap:10px"><?= $page_header_actions ?? '<a href="/#catalog" class="btn btn--line btn--sm">В каталог</a>' ?></div>
  </div>
</header>
