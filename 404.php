<?php
http_response_code(404);
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Страница не найдена — Perfume Space';
$page_description = 'Такой страницы нет. Перейдите в каталог оригинальной парфюмерии на распив Perfume Space.';
$page_robots = 'noindex, follow';
require __DIR__ . '/includes/header-simple.php';
?>
<main class="page page--center">
  <div class="container">
    <div class="eyebrow" style="margin-bottom:28px">Ошибка 404</div>
    <h1 class="h-display" style="margin-bottom:28px">Этот аромат <em>улетучился</em></h1>
    <p class="lead">Страница не найдена: возможно, ссылка устарела или в адресе опечатка. Все ароматы — в каталоге.</p>
    <div class="hero__ctas">
      <a href="/#catalog" class="btn btn--primary">Открыть каталог</a>
      <a href="/" class="btn btn--line">На главную</a>
    </div>
  </div>
</main>
</body>
</html>
