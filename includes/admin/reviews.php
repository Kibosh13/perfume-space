<?php /** Отзывы: ручное добавление и модерация */ ?>
        <div class="admin-card" style="margin-bottom:24px;">
          <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:8px;">Добавить отзыв вручную</h2>
          <p style="color:var(--text-secondary);margin-bottom:14px;">Сюда можно переписать отзывы с других площадок. Такой отзыв сразу публикуется на сайте.</p>
          <form method="post" class="form-grid">
            <input type="hidden" name="admin_action" value="add_review">
            <div class="two-col-grid">
              <div class="form-group"><label>Имя</label><input class="form-control" name="author" required></div>
              <div class="form-group"><label>Аромат (отзыв появится на его странице)</label><input class="form-control" name="perfume" placeholder="Начните вводить название" list="productList"></div>
            </div>
            <div class="form-group"><label>Оценка</label>
              <select class="form-control" name="rating"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select>
            </div>
            <div class="form-group"><label>Текст</label><textarea class="form-control" name="text" rows="4" required></textarea></div>
            <button class="btn btn--primary" type="submit">Опубликовать</button>
          </form>
        </div>
        <div class="admin-card">
          <h2 style="font-family:var(--font-serif);font-size:1.45rem;margin-bottom:14px;">Модерация</h2>
          <?php if (!$reviews): ?><p>Отзывов пока нет.</p><?php endif; ?>
          <?php foreach (array_reverse($reviews) as $rev): ?>
            <div style="border-top:1px solid var(--border-subtle);padding:14px 0;">
              <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <strong><?= h($rev['author'] ?? '') ?></strong>
                <span><?= (int)($rev['rating'] ?? 5) ?>/5 · <?= h($rev['status'] ?? '') ?> · <?= h($rev['created_at'] ?? '') ?></span>
              </div>
              <?php if (!empty($rev['perfume'])): ?><div style="color:var(--blue-ice);font-size:13px;"><?= h($rev['perfume']) ?><?= !empty($rev['product_id']) ? ' · <a href="/admin.php?tab=product&id=' . (int)$rev['product_id'] . '">карточка #' . (int)$rev['product_id'] . '</a>' : ' · <span class="muted">не привязан к аромату</span>' ?></div><?php endif; ?>
              <p style="margin:8px 0;"><?= h($rev['text'] ?? '') ?></p>
              <?php if (($rev['status'] ?? '') !== 'published'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="admin_action" value="moderate_review">
                  <input type="hidden" name="review_id" value="<?= h($rev['id'] ?? '') ?>">
                  <input type="hidden" name="status" value="published">
                  <button class="btn btn--line btn--sm" type="submit">Опубликовать</button>
                </form>
              <?php endif; ?>
              <?php if (($rev['status'] ?? '') !== 'rejected'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="admin_action" value="moderate_review">
                  <input type="hidden" name="review_id" value="<?= h($rev['id'] ?? '') ?>">
                  <input type="hidden" name="status" value="rejected">
                  <button class="btn btn--ghost btn--sm" type="submit">Скрыть</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
