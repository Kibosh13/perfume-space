/* Только для статической демо-версии (GitHub Pages): имитирует api.php в браузере, ничего никуда не отправляется. */
(function () {
  'use strict';
  const CFG = window.PS || {};
  const ROWS = window.PS_PRODUCTS || [];
  const BY_ID = new Map(ROWS.map((r) => [r[0], r]));
  const PROMOS = window.PS_DEMO_PROMOS || [];
  const VOL_IDX = { 5: 5, 10: 6, 15: 7 };
  const pad = (n, l = 2) => String(n).padStart(l, '0');
  const now = () => { const d = new Date(); return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
  const store = (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } };
  const put = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
  const nextId = (prefix) => {
    const d = new Date();
    const n = (Number(store('ps_demo_seq', 0)) || 0) + 1;
    put('ps_demo_seq', n);
    return `${prefix}-${String(d.getFullYear()).slice(2)}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(n, 4)}`;
  };
  const delay = (v) => new Promise((r) => setTimeout(() => r(v), 300));
  const findPromo = (code) => PROMOS.find((p) => p.active && p.code.toUpperCase() === String(code || '').trim().toUpperCase());
  const promoError = (p, sub) => {
    if (!p) return 'Такого промокода нет или он больше не действует';
    if (p.expires && new Date(p.expires + 'T23:59:59') < new Date()) return 'Срок действия промокода истёк';
    const used = store('ps_demo_used_promos', []);
    if (p.first_order && store('ps_orders', []).some((o) => o.type !== 'request')) return 'Промокод действует только на первый заказ';
    if (p.once && used.includes(p.code.toUpperCase())) return 'Этот промокод уже использован';
    if (p.min_sum && sub < p.min_sum) return `Промокод действует при заказе от ${p.min_sum.toLocaleString('ru-RU')} ₽`;
    return '';
  };
  const pub = (p) => ({ code: p.code.toUpperCase(), type: p.type, value: p.value, min_sum: p.min_sum || 0, title: p.title || '' });

  window.PS_API = (action, data) => {
    if (action === 'check_promo') {
      const p = findPromo(data.code);
      const err = promoError(p, Number(data.subtotal) || 0);
      if (err && !(p && err.startsWith('Промокод действует при заказе от'))) return delay({ ok: false, error: err });
      return delay({ ok: true, promo: pub(p) });
    }
    if (action === 'create_order') {
      const items = [];
      let subtotal = 0, qty = 0;
      (data.items || []).forEach((it) => {
        const r = BY_ID.get(Number(it.id));
        const base = r && r[VOL_IDX[it.volume]];
        if (!r || !r[8] || !base) return;
        const price = Math.round(Math.round(base * (100 - (r[12] || 0)) / 100) * (100 - ({ 10: 3, 15: 7 }[it.volume] || 0)) / 100);
        const q = Math.max(1, Math.min(20, Number(it.qty) || 1));
        subtotal += price * q; qty += q;
        items.push({ id: r[0], name: r[3], volume: String(it.volume), price, qty: q });
      });
      if (!items.length) return delay({ ok: false, error: 'Товары из корзины сейчас недоступны.' });
      const discount = qty >= (CFG.bundleMin || 5) ? Math.round(subtotal * (CFG.bundlePct || 10) / 100) : 0;
      let promoSum = 0;
      if (data.promo_code) {
        const p = findPromo(data.promo_code);
        const err = promoError(p, subtotal - discount);
        if (err) return delay({ ok: false, error: err });
        promoSum = Math.min(subtotal - discount, p.type === 'amount' ? p.value : Math.round((subtotal - discount) * p.value / 100));
        put('ps_demo_used_promos', [...store('ps_demo_used_promos', []), p.code.toUpperCase()]);
      }
      const after = subtotal - discount - promoSum;
      const points = Math.max(0, Math.min(Number(data.points_spend) || 0, Math.floor(after * 0.5)));
      const total = after - points;
      const gift = BY_ID.get(Number(data.gift_id));
      const book = data.order_mode === 'book';
      const order = {
        id: nextId(book ? 'BR' : 'PS'), type: book ? 'booking' : 'order', status: 'active', created_at: now(),
        delivery: data.delivery || '', payment: data.payment || '', items, subtotal, discount_bundle: discount,
        promo_code: promoSum ? String(data.promo_code).toUpperCase() : '', discount_promo: promoSum, total,
        gift_2ml: !!gift && after >= (CFG.giftFrom || 8000), gift_name: gift ? gift[3] : '', comment: data.comment || '',
      };
      return delay({ ok: true, order, message: `${book ? 'Бронь' : 'Заказ'} ${order.id} принят (демо-версия: заказ никуда не отправлен).` });
    }
    if (action === 'create_review') return delay({ ok: true, message: 'Отзыв отправлен на модерацию (демо-версия).' });
    if (action === 'vote_similar') return delay({ ok: true });
    if (action === 'create_request') {
      const order = { id: nextId('RQ'), type: 'request', status: 'active', created_at: now(), delivery: '', payment: '', items: [], total: 0, gift_2ml: false, comment: data.request_type || 'Заявка' };
      return delay({ ok: true, order, message: `Заявка ${order.id} принята (демо-версия: заявка никуда не отправлена).` });
    }
    if (action === 'my_orders') {
      const ids = new Set(data.ids || []);
      return delay({ ok: true, orders: store('ps_orders', []).filter((o) => ids.has(o.id)) });
    }
    return delay({ ok: false, error: 'Недоступно в демо-версии' });
  };
})();
