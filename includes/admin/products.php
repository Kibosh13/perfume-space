<?php /** Ароматы: добавление, массовое редактирование цен, CSV */ ?>
        <!-- Add New Product -->
        <div class="admin-card">
          <h2 style="font-family:var(--font-serif);font-size:1.45rem;margin-bottom:14px;">+ Добавить новый аромат в каталог</h2>
          <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;align-items:end;">
            <input type="hidden" name="admin_action" value="add_product">
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Бренд *</label>
              <input type="text" name="brand" class="admin-input-sm" placeholder="Amouage" required>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Полное название *</label>
              <input type="text" name="name" class="admin-input-sm" placeholder="Amouage Guidance" required>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">Категория</label>
              <select name="gender" class="admin-input-sm">
                <option value="Унисекс">Унисекс</option>
                <option value="Женские">Женские</option>
                <option value="Мужские">Мужские</option>
              </select>
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">5 мл (₽)</label>
              <input type="number" name="price_5" class="admin-input-sm" placeholder="1500">
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">10 мл (₽)</label>
              <input type="number" name="price_10" class="admin-input-sm" placeholder="3000">
            </div>
            <div>
              <label style="font-size:0.75rem;color:var(--text-secondary);">15 мл (₽)</label>
              <input type="number" name="price_15" class="admin-input-sm" placeholder="4500">
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
              <label style="font-size:0.8rem;"><input type="checkbox" name="in_stock" checked> В наличии</label>
              <label style="font-size:0.8rem;"><input type="checkbox" name="is_hit"> Хит</label>
            </div>
            <button type="submit" class="btn btn--primary btn--sm">Добавить</button>
          </form>
        </div>

        <div class="admin-card">
          <h2 style="font-family:var(--font-serif);font-size:1.45rem;margin-bottom:8px;">Массовое заполнение через Excel</h2>
          <p style="color:var(--text-secondary);margin-bottom:14px;font-size:14px">Скачайте таблицу, заполните в Excel колонки (аккорды, ноты, парфюмер, год, концентрация, стойкость 1–5, шлейф 1–5, сезоны, описание…) и загрузите обратно в формате CSV. Списки — через запятую. Строки сопоставляются по колонке id.</p>
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <a href="/admin.php?export=csv" class="btn btn--line btn--sm">Скачать каталог (.csv)</a>
            <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <input type="hidden" name="admin_action" value="import_csv">
              <input type="file" name="csv" accept=".csv,text/csv" class="admin-input-sm" style="width:auto" required>
              <button class="btn btn--primary btn--sm" type="submit">Загрузить CSV</button>
            </form>
          </div>
        </div>

        <!-- Search & Edit Products -->
        <div class="admin-card">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h2 style="font-family:var(--font-serif);font-size:1.45rem;">Цены и наличие <span class="muted small">— найдено <?= count($filtered_products) ?></span></h2>
            <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
              <input type="hidden" name="tab" value="products"><?php if (!empty($_GET['f'])): ?><input type="hidden" name="f" value="<?= h($_GET['f']) ?>"><?php endif; ?>
              <input type="search" name="q" value="<?= h($q) ?>" class="admin-input-sm" style="width:240px;" placeholder="Поиск по названию или бренду...">
              <button type="submit" class="btn btn--line btn--sm">Найти</button>
              <?php if ($q !== ''): ?>
                <a href="/admin.php?tab=products" class="btn btn--ghost btn--sm">Сброс</a>
              <?php endif; ?>
            </form>
          </div>
          <?php $fl = fn($f) => '/admin.php?' . http_build_query(array_filter(['tab' => 'products', 'q' => $q, 'f' => $f])); $cf = $_GET['f'] ?? ''; ?>
          <div class="adm-filters">
            <a class="tab-chip<?= $cf === '' ? ' active' : '' ?>" href="<?= h($fl('')) ?>">Все</a>
            <a class="tab-chip<?= $cf === 'out' ? ' active' : '' ?>" href="<?= h($fl('out')) ?>">Нет в наличии</a>
            <a class="tab-chip<?= $cf === 'nodesc' ? ' active' : '' ?>" href="<?= h($fl('nodesc')) ?>">Без нот и описания</a>
            <a class="tab-chip<?= $cf === 'nophoto' ? ' active' : '' ?>" href="<?= h($fl('nophoto')) ?>">Без фото</a>
          </div>

          <div style="overflow-x:auto;">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Фото</th>
                  <th>Бренд и Название</th>
                  <th>Пол</th>
                  <th>5 мл (₽)</th>
                  <th>10 мл (₽)</th>
                  <th>15 мл (₽)</th>
                  <th>Скидка %</th>
                  <th>Статус</th>
                  <th>Действие</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($display_products as $p): ?>
                  <tr>
                    <form method="post">
                      <input type="hidden" name="admin_action" value="update_product">
                      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="brand" value="<?= h($p['brand'] ?? '') ?>">
                      <input type="hidden" name="short_name" value="<?= h($p['short_name'] ?? '') ?>">
                      <input type="hidden" name="family" value="<?= h($p['family'] ?? '') ?>">
                      <td><?php if (!empty($p['image'])): ?><img src="<?= h($p['image']) ?>" alt="" width="48" height="48" loading="lazy" style="border-radius:4px;object-fit:cover"><?php else: ?><span class="muted">нет</span><?php endif; ?></td>
                      <td>
                        <div style="font-size:0.72rem;color:var(--blue-ice);font-weight:700;"><?= h($p['brand'] ?? '') ?></div>
                        <input type="text" name="name" value="<?= h($p['name'] ?? '') ?>" class="admin-input-sm" style="min-width:210px;">
                      </td>
                      <td>
                        <select name="gender" class="admin-input-sm">
                          <option value="Унисекс" <?= ($p['gender'] ?? '') === 'Унисекс' ? 'selected' : '' ?>>Унисекс</option>
                          <option value="Женские" <?= ($p['gender'] ?? '') === 'Женские' ? 'selected' : '' ?>>Женские</option>
                          <option value="Мужские" <?= ($p['gender'] ?? '') === 'Мужские' ? 'selected' : '' ?>>Мужские</option>
                        </select>
                      </td>
                      <td><input type="number" name="price_5" value="<?= (int)($p['volumes']['5'] ?? 0) ?>" class="admin-input-sm" style="width:78px;"></td>
                      <td><input type="number" name="price_10" value="<?= (int)($p['volumes']['10'] ?? 0) ?>" class="admin-input-sm" style="width:78px;"></td>
                      <td><input type="number" name="price_15" value="<?= (int)($p['volumes']['15'] ?? 0) ?>" class="admin-input-sm" style="width:78px;"></td>
                      <td><input type="number" name="discount" value="<?= (int)($p['discount'] ?? 0) ?>" class="admin-input-sm" style="width:62px;"></td>
                      <td style="white-space:nowrap;">
                        <label style="display:block;font-size:0.76rem;"><input type="checkbox" name="in_stock" <?= !empty($p['in_stock']) ? 'checked' : '' ?>> В наличии</label>
                        <label style="display:block;font-size:0.76rem;"><input type="checkbox" name="is_hit" <?= !empty($p['is_hit']) ? 'checked' : '' ?>> Хит</label>
                        <label style="display:block;font-size:0.76rem;"><input type="checkbox" name="is_starter" <?= !empty($p['is_starter']) ? 'checked' : '' ?>> Старт-сет</label>
                      </td>
                      <td style="white-space:nowrap;">
                        <button type="submit" class="btn btn--primary btn--sm" style="padding:6px 12px;">Сохранить</button>
                        <a href="/admin.php?tab=product&id=<?= (int)$p['id'] ?>" class="btn btn--line btn--sm" style="padding:6px 12px;">Карточка</a>
                      </td>
                    </form>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($p_pages > 1): ?><div class="adm-pager"><?php for ($i = 1; $i <= $p_pages; $i++): ?><a class="<?= $i === $p_page ? 'is-active' : '' ?>" href="/admin.php?<?= h(http_build_query(array_filter(['tab' => 'products', 'q' => $q, 'f' => $_GET['f'] ?? '', 'p' => $i > 1 ? $i : '']))) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
        </div>

