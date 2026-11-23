<?php
/** Библиотека парфюмера: статьи */
$all = articles(false);
$edit = $_GET['slug'] ?? null;
if ($edit !== null):
    $a = ['slug' => '', 'title' => '', 'category' => '', 'date' => date('Y-m-d'), 'published' => false, 'cover' => '', 'excerpt' => '', 'body' => "## Подзаголовок\nТекст раздела.\n\n[товары: ]", 'products' => [], 'images' => [], 'meta_title' => '', 'meta_description' => ''];
    foreach ($all as $x) if ($edit !== '' && $x['slug'] === $edit) $a = array_merge($a, $x);
    $cat_names = array_unique(array_filter(array_merge(array_column($all, 'category'), ['Гид по распиву', 'Основы', 'Советы', 'Подборки', 'Ноты и ингредиенты', 'Бренды и парфюмеры', 'Новости'])));
?>
<div class="adm-page-head">
  <div><a href="/admin.php?tab=articles" class="back">← Все статьи</a><h1><?= $a['slug'] ? h($a['title']) : 'Новая статья' ?></h1></div>
  <?php if ($a['slug']): ?><a class="btn btn--line btn--sm" href="<?= h(article_url($a)) ?>" target="_blank"><?= $a['published'] ? 'Открыть на сайте' : 'Предпросмотр недоступен (черновик)' ?></a><?php endif; ?>
</div>
<form method="post" enctype="multipart/form-data" class="adm-grid adm-grid--editor" id="articleForm">
  <input type="hidden" name="admin_action" value="save_article">
  <input type="hidden" name="orig_slug" value="<?= h($a['slug']) ?>">
  <div>
    <section class="admin-card form-grid">
      <div class="form-group"><label>Заголовок</label><input class="form-control form-control--lg" name="title" value="<?= h($a['title']) ?>" required></div>
      <div class="form-group"><label>Анонс (под заголовком и в списке статей)</label><textarea class="form-control" name="excerpt" rows="2"><?= h($a['excerpt']) ?></textarea></div>
      <div class="form-group">
        <label>Текст статьи</label>
        <div class="editor-bar">
          <button type="button" data-ins="h2">Подзаголовок</button>
          <button type="button" data-ins="h3">Подзаголовок 2</button>
          <button type="button" data-ins="b">Жирный</button>
          <button type="button" data-ins="li">Список</button>
          <button type="button" data-ins="link">Ссылка</button>
          <span class="editor-bar__sep"></span>
          <input class="admin-input-sm" id="prodPick" list="productList" placeholder="Аромат для вставки…">
          <button type="button" data-ins="products" class="is-accent">+ Карточки ароматов</button>
        </div>
        <textarea class="form-control editor" name="body" id="articleBody" rows="26"><?= h($a['body']) ?></textarea>
        <small class="hint">«## » — подзаголовок (попадёт в содержание), «- » — пункт списка, **жирный**, [текст](/ссылка/). Карточки ароматов: строка <b>[товары: 846, 594]</b> — выберите аромат в поле выше и нажмите «+ Карточки». Картинка: <b>![Подпись](/адрес)</b>.</small>
      </div>
    </section>
    <section class="admin-card form-grid">
      <h2>Картинки для текста</h2>
      <?php if ($a['images']): ?><div class="adm-images"><?php foreach ($a['images'] as $img): ?><figure><img src="<?= h($img) ?>" alt=""><figcaption><button type="button" class="btn btn--line btn--sm" data-insimg="<?= h($img) ?>">Вставить в текст</button></figcaption></figure><?php endforeach; ?></div><?php endif; ?>
      <div class="form-group"><label>Загрузить картинки (после сохранения появятся выше)</label><input type="file" name="body_images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-control"></div>
    </section>
  </div>
  <div>
    <section class="admin-card form-grid">
      <label class="switch-line"><input type="checkbox" name="published" <?= $a['published'] ? 'checked' : '' ?>> Опубликована</label>
      <div class="form-group"><label>Дата</label><input class="form-control" type="date" name="date" value="<?= h($a['date']) ?>"></div>
      <div class="form-group"><label>Рубрика</label><input class="form-control" name="category" value="<?= h($a['category']) ?>" list="artCats"><datalist id="artCats"><?php foreach ($cat_names as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist></div>
      <div class="form-group"><label>Адрес</label><input class="form-control" name="slug" value="<?= h($a['slug']) ?>" placeholder="заполнится из заголовка"><small class="hint">/statiy/<?= h($a['slug'] ?: '…') ?>/</small></div>
      <div class="form-group"><label>Обложка</label>
        <?php if ($a['cover']): ?><img src="<?= h($a['cover']) ?>" alt="" class="adm-cover"><label class="small"><input type="checkbox" name="remove_cover" value="1"> удалить обложку</label><?php endif; ?>
        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="form-control">
      </div>
      <div class="form-group"><label>Ароматы в конце статьи</label><textarea class="form-control" name="products" rows="4" placeholder="id или названия, каждый с новой строки"><?= h(implode("\n", array_map(fn($id) => catalog()[(int)$id]['name'] ?? $id, (array)$a['products']))) ?></textarea><small class="hint">Блок «Ароматы из статьи». Необязательно — карточки можно вставлять прямо в текст.</small></div>
    </section>
    <section class="admin-card form-grid">
      <h2>SEO</h2>
      <div class="form-group"><label>Title</label><input class="form-control" name="meta_title" value="<?= h($a['meta_title']) ?>" placeholder="По умолчанию — заголовок статьи"></div>
      <div class="form-group"><label>Description</label><textarea class="form-control" name="meta_description" rows="3" placeholder="По умолчанию — анонс"><?= h($a['meta_description']) ?></textarea></div>
    </section>
    <button class="btn btn--primary btn--block" type="submit">Сохранить статью</button>
  </div>
</form>
<?php if ($a['slug']): ?>
<form method="post" onsubmit="return confirm('Удалить статью?')"><input type="hidden" name="admin_action" value="delete_article"><input type="hidden" name="slug" value="<?= h($a['slug']) ?>"><button class="btn btn--ghost btn--sm">Удалить статью</button></form>
<?php endif; ?>
<datalist id="productList"><?php foreach (catalog() as $pp): ?><option value="<?= h($pp['name']) ?> #<?= (int)$pp['id'] ?>"><?php endforeach; ?></datalist>
<script>
(function () {
  const ta = document.getElementById('articleBody');
  const wrap = (before, after = '', ph = '') => {
    const s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || ph;
    ta.setRangeText(before + sel + after, s, e, 'end'); ta.focus();
  };
  const line = (text) => {
    const s = ta.selectionStart;
    const pre = s > 0 && ta.value[s - 1] !== '\n' ? '\n' : '';
    ta.setRangeText(pre + text + '\n', s, ta.selectionEnd, 'end'); ta.focus();
  };
  document.querySelector('.editor-bar').addEventListener('click', (e) => {
    const b = e.target.closest('[data-ins]'); if (!b) return;
    const k = b.dataset.ins;
    if (k === 'h2') wrap('\n## ', '\n', 'Подзаголовок');
    if (k === 'h3') wrap('\n### ', '\n', 'Подзаголовок');
    if (k === 'b') wrap('**', '**', 'текст');
    if (k === 'li') wrap('\n- ', '', 'пункт списка');
    if (k === 'link') wrap('[', '](/category/)', 'текст ссылки');
    if (k === 'products') {
      const m = (document.getElementById('prodPick').value || '').match(/#(\d+)\s*$/);
      if (!m) { alert('Выберите аромат из списка в поле слева от кнопки'); return; }
      // если курсор стоит в строке [товары: …] — дописываем в неё
      const v = ta.value, s = ta.selectionStart;
      const ls = v.lastIndexOf('\n', s - 1) + 1, le = v.indexOf('\n', s) === -1 ? v.length : v.indexOf('\n', s);
      const cur = v.slice(ls, le);
      const mm = cur.match(/^\[товары:\s*(.*)\]$/);
      if (mm) {
        const ids = mm[1].split(',').map((x) => x.trim()).filter(Boolean); ids.push(m[1]);
        ta.setRangeText('[товары: ' + ids.join(', ') + ']', ls, le, 'end');
      } else line('[товары: ' + m[1] + ']');
      document.getElementById('prodPick').value = '';
    }
  });
  document.querySelectorAll('[data-insimg]').forEach((b) => b.addEventListener('click', () => line('![Подпись к фото](' + b.dataset.insimg + ')')));
})();
</script>
<?php else: ?>
<div class="adm-page-head">
  <h1>Библиотека парфюмера</h1>
  <div style="display:flex;gap:8px"><a class="btn btn--line btn--sm" href="/statiy/" target="_blank">На сайте</a><a class="btn btn--primary btn--sm" href="/admin.php?tab=articles&slug=">+ Новая статья</a></div>
</div>
<section class="admin-card">
  <?php if (!$all): ?><p class="muted">Статей пока нет.</p><?php else: ?>
  <table class="admin-table">
    <thead><tr><th></th><th>Статья</th><th>Рубрика</th><th>Дата</th><th>Ароматов</th><th>Статус</th></tr></thead>
    <?php foreach ($all as $x): ?>
      <tr class="row-link" onclick="location='/admin.php?tab=articles&slug=<?= urlencode($x['slug']) ?>'">
        <td style="width:84px"><?php if (!empty($x['cover'])): ?><img src="<?= h($x['cover']) ?>" alt="" style="width:72px;height:46px;object-fit:cover;border-radius:4px"><?php endif; ?></td>
        <td><b><?= h($x['title']) ?></b><div class="muted small">/statiy/<?= h($x['slug']) ?>/</div></td>
        <td><?= h($x['category'] ?? '') ?></td>
        <td class="small"><?= h(ru_date($x['date'] ?? '')) ?></td>
        <td><?= count(article_all_products($x)) ?></td>
        <td><?= !empty($x['published']) ? '<span class="st st--completed">Опубликована</span>' : '<span class="st st--cancelled">Черновик</span>' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>
<?php endif; ?>
