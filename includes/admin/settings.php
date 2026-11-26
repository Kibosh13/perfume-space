<?php
/** Настройки сайта: главная и тексты, акции, SEO, контакты и реквизиты, уведомления и доступ */
$sub = $_GET['sub'] ?? 'home';
$subs = ['home' => 'Главная и тексты', 'promo' => 'Акции и бонусы', 'seo' => 'SEO', 'contacts' => 'Контакты и реквизиты', 'access' => 'Уведомления, аналитика, пароль', 'closed' => 'Закрыть сайт'];
if (!isset($subs[$sub])) $sub = 'home';
$c = $config;
$v = fn(string $path) => h((string)array_reduce(explode('.', $path), fn($carry, $k) => is_array($carry) ? ($carry[$k] ?? '') : '', $c));
$n = fn(string $path) => 'cfg[' . implode('][', explode('.', $path)) . ']';
$field = function (string $label, string $path, string $type = 'text', string $hint = '', string $ph = '') use ($v, $n) {
    $val = $v($path);
    $input = $type === 'textarea'
        ? '<textarea class="form-control" name="' . $n($path) . '" rows="3" placeholder="' . h($ph) . '">' . $val . '</textarea>'
        : '<input class="form-control" type="' . $type . '" name="' . $n($path) . '" value="' . $val . '" placeholder="' . h($ph) . '">';
    $tbd = str_contains(html_entity_decode($val), '[заполнить') ? ' <span class="tbd">нужно заполнить</span>' : '';
    return '<div class="form-group"><label>' . h($label) . $tbd . '</label>' . $input . ($hint ? '<small class="hint">' . $hint . '</small>' : '') . '</div>';
};
$default_faq = [
    ['q' => 'Это оригинальная парфюмерия?', 'a' => 'Да. Оригинальность продукции подтверждена декларацией соответствия и маркировкой «Честный ЗНАК». Если остались вопросы по конкретному аромату — напишите нам, расскажем подробнее.'],
    ['q' => 'Что такое распив?', 'a' => 'Это часть аромата из оригинального флакона, перелитая в атомайзер на 5, 10 или 15 мл. Так можно попробовать и носить дорогой аромат без покупки целого флакона.'],
    ['q' => 'Сколько стоит доставка?', 'a' => 'Доставка по всей России бесплатная. Обычно отправляем заказ в течение суток после оформления — через СДЭК, Почту России или Яндекс Доставку.'],
    ['q' => 'Как получить подарок и скидку?', 'a' => 'За каждые {gift_from} в заказе после скидок — пробник 2 мл в подарок: выберите аромат прямо в корзине. При покупке от {bundle_min} позиций скидка {bundle_pct}% применяется автоматически.'],
    ['q' => 'Что делать, если аромата нет в наличии?', 'a' => 'Нажмите «Забронировать» на странице аромата — мы сообщим, когда аромат появится, и закрепим его за вами.'],
];
$faq = $config['faq'] ?? $default_faq;
?>
<div class="adm-page-head"><h1>Настройки сайта</h1></div>
<div class="seg-tabs">
  <?php foreach ($subs as $k => $l): ?><a href="/admin.php?tab=settings&sub=<?= $k ?>" class="<?= $sub === $k ? 'is-active' : '' ?>"><?= $l ?></a><?php endforeach; ?>
</div>
<?php if ($sub === 'closed'):
    $mt = (array)($config['maintenance'] ?? []);
    $preview = !empty($mt['token']) ? site_url() . '/?preview=' . $mt['token'] : '';
?>
<form method="post" class="admin-card form-grid settings-form">
  <input type="hidden" name="admin_action" value="save_maintenance">
  <h2>Временно закрыть сайт</h2>
  <p class="muted">Пока сайт закрыт, посетители на любой странице видят заглушку с вашими контактами, а оформить заказ нельзя. Поисковики получают ответ «временно недоступен» и не выкидывают сайт из поиска. Вы, пока вошли в админку, видите сайт как обычно.</p>
  <label class="check-line" style="font-size:16px;font-weight:600"><input type="checkbox" name="enabled" value="1" <?= !empty($mt['enabled']) ? 'checked' : '' ?>> Сайт закрыт для посетителей</label>
  <div class="form-group"><label>Заголовок заглушки</label><input class="form-control" name="title" value="<?= h($mt['title'] ?? '') ?>" placeholder="Сайт скоро откроется"></div>
  <div class="form-group"><label>Текст заглушки</label><textarea class="form-control" name="text" rows="3" placeholder="Мы готовим обновлённый магазин оригинальной парфюмерии на распив. Совсем скоро всё заработает — а пока с нами можно связаться напрямую."><?= h($mt['text'] ?? '') ?></textarea><small class="hint">Под текстом автоматически показываются телефон, мессенджеры и почта из «Контакты и реквизиты».</small></div>
  <?php if ($preview): ?>
  <div class="form-group"><label>Ссылка предпросмотра</label><input class="form-control" value="<?= h($preview) ?>" readonly onclick="this.select()"><small class="hint">Откройте её на любом устройстве — и там сайт будет виден, даже когда он закрыт (60 дней). Удобно, чтобы показать сайт кому-то без пароля от админки.</small></div>
  <label class="check-line"><input type="checkbox" name="new_token" value="1"> Сделать новую ссылку предпросмотра (старая перестанет работать)</label>
  <?php endif; ?>
  <p><a href="/" target="_blank">Открыть сайт ↗</a> · чтобы увидеть заглушку глазами посетителя, откройте сайт в режиме инкогнито.</p>
  <div class="sticky-save"><button type="submit" class="btn btn--primary">Сохранить</button></div>
</form>
<?php return; endif; ?>
<form method="post" enctype="multipart/form-data" class="admin-card form-grid settings-form">
  <input type="hidden" name="admin_action" value="save_settings">

  <?php if ($sub === 'home'): ?>
    <h2>Первый экран</h2>
    <?= $field('Главный заголовок (H1)', 'hero_title') ?>
    <?= $field('Подзаголовок', 'hero_subtitle', 'textarea') ?>
    <div class="two-col-grid"><?= $field('График работы', 'work_hours', 'text', 'Показывается в бегущей строке, в блоке заявки и в контактах.') ?></div>
    <p class="hint">Баннеры с промокодами на первом экране включаются в разделе «Промокоды» (галочка «Баннер на первом экране»).</p>
    <h2>Тексты разделов</h2>
    <?= $field('«Только оригинальная продукция» — текст блока', 'about_text', 'textarea') ?>
    <?= $field('«Доставка и оплата» — вводный текст', 'delivery_text', 'textarea') ?>
    <h2>Вопросы и ответы на главной</h2>
    <p class="hint">Пустые строки удаляются. Подстановки: {gift_from} — порог пробника, {bundle_min} и {bundle_pct} — скидка за количество.</p>
    <div class="faq-edit">
      <?php foreach (array_merge($faq, [['q' => '', 'a' => ''], ['q' => '', 'a' => '']]) as $i => $f): ?>
        <div class="faq-edit__row">
          <input class="form-control" name="faq_q[<?= $i ?>]" value="<?= h($f['q']) ?>" placeholder="Вопрос">
          <textarea class="form-control" name="faq_a[<?= $i ?>]" rows="2" placeholder="Ответ"><?= h($f['a']) ?></textarea>
        </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($sub === 'promo'): ?>
    <h2>Пробник в подарок</h2>
    <div class="two-col-grid">
      <?= $field('Пробник 2 мл за каждые, ₽', 'promos.gift_threshold', 'number', 'Считается от итоговой суммы после всех скидок и баллов: 2 порога — 2 пробника и т. д.') ?>
      <?= $field('Заголовок акции (блок «Акции» на главной)', 'promos.gift_title') ?>
    </div>
    <h2>Скидка за количество</h2>
    <div class="two-col-grid">
      <?= $field('От скольких позиций в заказе', 'promos.bundle_min_items', 'number') ?>
      <?= $field('Размер скидки, %', 'promos.bundle_discount_percent', 'number') ?>
    </div>
    <h2>Бонусы</h2>
    <div class="two-col-grid">
      <?= $field('Начисляется с покупки, %', 'promos.loyalty_percent', 'number') ?>
      <?= $field('Можно оплатить баллами до, % заказа', 'promos.loyalty_max_share', 'number') ?>
    </div>
    <?= $field('Заголовок (блок «Акции» на главной)', 'promos.loyalty_title') ?>
    <?= $field('Условия (текст в блоке «Акции»)', 'promos.loyalty_desc', 'textarea') ?>

  <?php elseif ($sub === 'seo'): ?>
    <h2>Главная страница</h2>
    <?= $field('Title', 'seo.home.title', 'text', 'До 70 символов. Пусто — стандартный заголовок.', 'Perfume Space — оригинальная парфюмерия на распив от 5 мл') ?>
    <?= $field('Description', 'seo.home.description', 'textarea', 'До 160 символов.', 'Оригинальная нишевая и люксовая парфюмерия на распив 5, 10 и 15 мл…') ?>
    <div class="form-group"><label>Картинка для соцсетей и мессенджеров (1200×630)</label>
      <?php if (!empty($config['seo']['home']['og_image'])): ?><img src="<?= h($config['seo']['home']['og_image']) ?>" alt="" class="adm-cover"><?php endif; ?>
      <input type="file" name="og_image" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
    <h2>Список брендов (/brands/)</h2>
    <div class="two-col-grid"><?= $field('Title', 'seo.brands.title') ?><?= $field('Description', 'seo.brands.description') ?></div>
    <h2>Все подборки (/category/)</h2>
    <div class="two-col-grid"><?= $field('Title', 'seo.collections.title') ?><?= $field('Description', 'seo.collections.description') ?></div>
    <h2>Библиотека парфюмера (/statiy/)</h2>
    <div class="two-col-grid"><?= $field('Title', 'seo.articles.title') ?><?= $field('Description', 'seo.articles.description') ?></div>
    <div class="two-col-grid"><?= $field('Заголовок H1', 'seo.articles.h1', 'text', '', 'Библиотека парфюмера') ?><?= $field('Вступление', 'seo.articles.intro') ?></div>
    <div class="seo-note">
      <b>Где ещё настраивается SEO:</b> у каждого аромата, бренда, подборки, статьи и информационной страницы есть свои поля Title и Description в их карточках.
      Карта сайта обновляется сама: <a href="/sitemap.xml" target="_blank">/sitemap.xml</a>. Код подтверждения Яндекс Вебмастера и Метрика — во вкладке «Уведомления, аналитика, пароль».
    </div>

  <?php elseif ($sub === 'contacts'): ?>
    <h2>Контакты</h2>
    <div class="two-col-grid">
      <?= $field('Почта', 'contacts.email') ?>
      <?= $field('Телефон', 'contacts.phone') ?>
      <?= $field('Telegram (ссылка)', 'contacts.telegram') ?>
      <?= $field('WhatsApp (ссылка)', 'contacts.whatsapp') ?>
      <?= $field('ВКонтакте (ссылка)', 'contacts.vk') ?>
      <?= $field('Профиль на Авито', 'contacts.avito_url') ?>
    </div>
    <h2>Реквизиты продавца</h2>
    <p class="hint">Подставляются в оферту, политику, реквизиты и подвал сайта.</p>
    <div class="two-col-grid">
      <?= $field('Наименование', 'legal.entity') ?>
      <?= $field('ИНН', 'legal.inn') ?>
      <?= $field('ОГРНИП', 'legal.ogrnip') ?>
      <?= $field('Адрес', 'legal.address') ?>
    </div>
    <?= $field('Номер декларации соответствия ЕАЭС', 'legal.declaration_number') ?>

  <?php elseif ($sub === 'access'): ?>
    <h2>Куда приходят заказы и заявки</h2>
    <div class="two-col-grid">
      <?= $field('Почта для заказов', 'integrations.order_email') ?>
      <div></div>
      <?= $field('Токен Telegram-бота', 'integrations.telegram_bot_token', 'text', 'Создайте бота у @BotFather и вставьте токен.') ?>
      <?= $field('Chat ID менеджера в Telegram', 'integrations.telegram_chat_id', 'text', 'Напишите боту, затем узнайте ID у @userinfobot.') ?>
    </div>
    <h2>Аналитика</h2>
    <div class="two-col-grid">
      <?= $field('Номер счётчика Яндекс Метрики', 'integrations.yandex_metrika_id') ?>
      <?= $field('Код подтверждения Яндекс Вебмастера', 'integrations.yandex_webmaster_code') ?>
    </div>
    <h2>Пароль админки</h2>
    <?php if (empty($config['admin_password_hash'])): ?><p class="tbd">Сейчас действует стандартный пароль — обязательно смените его.</p><?php endif; ?>
    <div class="two-col-grid">
      <div class="form-group"><label>Новый пароль</label><input type="password" name="new_admin_password" class="form-control" autocomplete="new-password" minlength="8" placeholder="минимум 8 символов"><small class="hint">Оставьте пустым, чтобы не менять.</small></div>
    </div>
  <?php endif; ?>

  <div class="sticky-save"><button type="submit" class="btn btn--primary">Сохранить</button></div>
</form>
