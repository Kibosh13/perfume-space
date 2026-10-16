/* Static demo build only: emulates api.php in the browser, nothing is sent anywhere. */
(function () {
  'use strict';
  const CFG = window.PS || {};
  const BY_ID = new Map((CFG.products || []).map((r) => [r[0], r]));
  const VOL_IDX = { 5: 5, 10: 6, 15: 7 };
  const pad = (n, l = 2) => String(n).padStart(l, '0');
  const now = () => { const d = new Date(); return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
  const nextId = (prefix) => {
    const d = new Date();
    const n = (Number(localStorage.getItem('ps_demo_seq')) || 0) + 1;
    localStorage.setItem('ps_demo_seq', n);
    return `${prefix}-${String(d.getFullYear()).slice(2)}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(n, 4)}`;
  };
  const stored = () => { try { return JSON.parse(localStorage.getItem('ps_orders')) || []; } catch (e) { return []; } };
  const delay = (v) => new Promise((r) => setTimeout(() => r(v), 350));

  window.PS_API = (action, data) => {
    if (action === 'create_order') {
      const items = [];
      let subtotal = 0, qty = 0;
      (data.items || []).forEach((it) => {
        const r = BY_ID.get(Number(it.id));
        const base = r && r[VOL_IDX[it.volume]];
        if (!r || !r[8] || !base) return;
        const price = Math.round(base * (100 - (r[12] || 0)) / 100);
        const q = Math.max(1, Math.min(20, Number(it.qty) || 1));
        subtotal += price * q; qty += q;
        items.push({ id: r[0], name: r[3], volume: String(it.volume), price, qty: q });
      });
      if (!items.length) return delay({ ok: false, error: 'Товары из корзины сейчас недоступны.' });
      const discount = qty >= (CFG.bundleMin || 5) ? Math.round(subtotal * (CFG.bundlePct || 10) / 100) : 0;
      const total = subtotal - discount;
      const book = data.order_mode === 'book';
      const order = {
        id: nextId(book ? 'BR' : 'PS'), type: book ? 'booking' : 'order', status: 'active', created_at: now(),
        delivery: data.delivery || '', payment: data.payment || '', items, subtotal, discount_bundle: discount, total,
        gift_2ml: total >= (CFG.giftFrom || 8000), comment: '',
      };
      return delay({ ok: true, order, message: `${book ? 'Бронь' : 'Заказ'} ${order.id} принят (демо-версия: заказ никуда не отправлен).` });
    }
    if (action === 'create_request') {
      const order = { id: nextId('RQ'), type: 'request', status: 'active', created_at: now(), delivery: '', payment: '', items: [], total: 0, gift_2ml: false, comment: data.request_type || 'Заявка' };
      return delay({ ok: true, order, message: `Заявка ${order.id} принята (демо-версия: заявка никуда не отправлена).` });
    }
    if (action === 'my_orders') {
      const ids = new Set(data.ids || []);
      return delay({ ok: true, orders: stored().filter((o) => ids.has(o.id)) });
    }
    return delay({ ok: false, error: 'Недоступно в демо-версии' });
  };
})();
