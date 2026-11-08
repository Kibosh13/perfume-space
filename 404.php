<?php
http_response_code(404);
require_once __DIR__ . '/includes/layout.php';
layout_start([
    'page' => '404',
    'title' => 'Страница не найдена — Perfume Space',
    'description' => 'Такой страницы нет. Перейдите в каталог оригинальной парфюмерии на распив Perfume Space.',
    'robots' => 'noindex, follow',
]);
?>
<main class="page page--center">
  <div class="container">
    <div class="eyebrow" style="margin-bottom:28px">Ошибка 404</div>
    <h1 class="h-display" style="margin-bottom:28px">Этот аромат <em>улетучился</em></h1>
    <p class="lead">Страница не найдена: возможно, ссылка устарела или в адресе опечатка. Попробуйте поиск — он понимает русское написание и опечатки.</p>
    <div class="hero__ctas">
      <button type="button" class="btn btn--primary" data-open="search">Найти аромат</button>
      <a href="/#catalog" class="btn btn--line">Открыть каталог</a>
    </div>
  </div>
</main>
<?php layout_end(['page' => '404']);
