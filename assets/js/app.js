(function () {
  'use strict';

  const CFG = window.PS || {};
  const PRODUCTS = (CFG.products || []).map((r) => ({
    id: r[0], brand: r[1], short: r[2], name: r[3], gender: r[4],
    volumes: { 5: r[5], 10: r[6], 15: r[7] },
    inStock: !!r[8], hit: !!r[9], image: r[10], family: r[11], discount: r[12] || 0, newness: r[13] || 0,
  })).map((p) => {
    p.base = { ...p.volumes };
    if (p.discount) Object.keys(p.volumes).forEach((v) => { if (p.volumes[v]) p.volumes[v] = Math.round(p.volumes[v] * (100 - p.discount) / 100); });
    const prices = Object.values(p.volumes).filter(Boolean);
    p.min = prices.length ? Math.min(...prices) : 0;
    return p;
  });
  const BY_ID = new Map(PRODUCTS.map((p) => [p.id, p]));
  const GIFT_FROM = CFG.giftFrom || 8000;
  const BUNDLE_MIN = CFG.bundleMin || 5;
  const BUNDLE_PCT = CFG.bundlePct || 10;
  const LOYALTY_PCT = CFG.loyaltyPct || 1;
  const LOYALTY_MAX = CFG.loyaltyMax || 50;
  const PROMOS = CFG.promos || [];
  const PAGE = 24;
  const PLACEHOLDER = '/perfume-space/assets/img/atomizer.webp';

  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const load = (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } };
  const save = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
  const money = (n) => Number(n || 0).toLocaleString('ru-RU') + ' ₽';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (id) => `<svg class="icon"><use href="#i-${id}"/></svg>`;
  const goal = (name, params) => { if (window.ym && window.PS_METRIKA_ID) window.ym(window.PS_METRIKA_ID, 'reachGoal', name, params || {}); };

  const state = {
    q: '', gender: '', volume: '', brand: '', min: '', max: '', stock: false, fav: false, sort: 'popular',
    shown: PAGE,
    picked: {},
    cart: load('ps_cart', []).filter((i) => BY_ID.has(i.id)),
    favs: load('ps_favs', []).filter((id) => BY_ID.has(id)),
    orders: load('ps_orders', []),
    notes: load('ps_notes', []),
    phone: load('ps_phone', ''),
    ordersTab: 'active',
    checkout: false,
    giftId: load('ps_gift', 0),
    pickingGift: false,
    giftQuery: '',
    promo: load('ps_promo', ''),
    usePoints: false,
    user: load('ps_user', null),
  };

  /* ---------- Product helpers ---------- */
  const VOL_OFF = { 5: 0, 10: 3, 15: 7 };
  const volPrice = (p, vol) => {
    const base = p.volumes[vol] || 0;
    const off = VOL_OFF[vol] || 0;
    return off ? Math.round(base * (100 - off) / 100) : base;
  };
  const volBtns = (p) => {
    const v = volOf(p);
    return ['5', '10', '15'].map((x) => {
      const off = VOL_OFF[x] || 0;
      return `<button type="button" class="vol ${x === v && p.volumes[x] ? 'is-active' : ''}" data-vol="${x}" data-id="${p.id}" ${p.volumes[x] && p.inStock ? '' : 'disabled'}>${x} мл${off && p.volumes[x] ? `<span class="vol__off">−${off}%</span>` : ''}</button>`;
    }).join('');
  };

  const volOf = (p) => {
    const v = state.picked[p.id];
    if (v && p.volumes[v]) return v;
    if (state.volume && p.volumes[state.volume]) return state.volume;
    return ['5', '10', '15'].find((x) => p.volumes[x]) || '5';
  };

  const media = (p, cls) => {
    if (p.image) return `<img src="${esc(p.image)}" alt="${esc(p.name)} — распив" loading="lazy" width="480" height="480">`;
    return `<img src="${PLACEHOLDER}" alt="" loading="lazy" width="520" height="520"><span class="${cls}">${esc(p.brand)}</span>`;
  };

  const cardHtml = (p) => {
    const v = volOf(p);
    const price = volPrice(p, v);
    const old = price < (p.volumes[v] || 0) ? p.volumes[v] : (p.discount ? p.base[v] : 0);
    const fav = state.favs.includes(p.id);
    const vols = volBtns(p);
    const badges = [p.discount && p.inStock ? `<span class="badge badge--sale">−${p.discount}%</span>` : '', p.hit && p.inStock ? '<span class="badge badge--hit">Хит</span>' : '', !p.inStock ? '<span class="badge badge--out">Нет в наличии</span>' : ''].join('');
    return `
      <article class="card ${p.inStock ? '' : 'card__out'}">
        <div class="card__media ${p.image ? '' : 'card__media--empty'}" data-view="${p.id}">
          <div class="card__badges">${badges}</div>
          ${media(p, 'card__ph-brand')}
        </div>
        <button type="button" class="card__fav ${fav ? 'is-active' : ''}" data-fav="${p.id}" aria-label="${fav ? 'Убрать из избранного' : 'В избранное'}">${icon('heart')}</button>
        <div class="card__brand">${esc(p.brand)}</div>
        <h3 class="card__name" data-view="${p.id}">${esc(p.short)}</h3>
        <div class="vols">${vols}</div>
        <div class="card__foot">
          ${p.inStock
            ? `<div class="price">${money(price)}${old ? `<s>${money(old)}</s>` : ''}<small>за ${v} мл</small></div>
               <div class="card__actions">
                 <button type="button" class="icon-btn" data-book="${p.id}" aria-label="Забронировать" title="Забронировать">${icon('box')}</button>
                 <button type="button" class="icon-btn icon-btn--primary" data-add="${p.id}" aria-label="В корзину" title="В корзину">${icon('bag')}</button>
               </div>`
            : `<div class="price"><small>Сейчас нет в наличии</small></div>
               <button type="button" class="card__notify" data-book="${p.id}">Забронировать</button>`}
        </div>
      </article>`;
  };

  /* ---------- Catalog ---------- */
  const filtered = () => {
    const q = state.q.trim().toLowerCase();
    const min = state.min === '' ? null : Number(state.min);
    const max = state.max === '' ? null : Number(state.max);
    const list = PRODUCTS.filter((p) => {
      if (state.gender && p.gender !== state.gender) return false;
      if (state.brand && p.brand !== state.brand) return false;
      if (state.volume && !p.volumes[state.volume]) return false;
      if (state.stock && !p.inStock) return false;
      if (state.fav && !state.favs.includes(p.id)) return false;
      if (min !== null || max !== null) {
        const price = state.volume ? volPrice(p, state.volume) : p.min;
        if (!price || (min !== null && price < min) || (max !== null && price > max)) return false;
      }
      if (q && !(p.name + ' ' + p.brand).toLowerCase().includes(q)) return false;
      return true;
    });
    const price = (p) => (state.volume ? volPrice(p, state.volume) : p.min) || 1e9;
    const sorters = {
      popular: (a, b) => (b.inStock - a.inStock) || (b.hit - a.hit) || (!!b.image - !!a.image) || a.id - b.id,
      new: (a, b) => (b.inStock - a.inStock) || (b.newness - a.newness) || (b.id - a.id),
      price_asc: (a, b) => (b.inStock - a.inStock) || price(a) - price(b),
      price_desc: (a, b) => (b.inStock - a.inStock) || price(b) - price(a),
      name: (a, b) => a.name.localeCompare(b.name),
    };
    return list.sort(sorters[state.sort] || sorters.popular);
  };

  const renderCatalog = () => {
    const list = filtered();
    const slice = list.slice(0, state.shown);
    $('#count').innerHTML = `Найдено <b>${list.length}</b> ${plural(list.length, 'аромат', 'аромата', 'ароматов')}`;
    $('#grid').innerHTML = slice.length ? slice.map(cardHtml).join('') : `
      <div class="empty"><h3>Ничего не нашлось</h3><p>Измените фильтры или оставьте заявку — поищем аромат для вас.</p>
      <button type="button" class="btn btn--line btn--sm" data-reset>Сбросить фильтры</button></div>`;
    const left = list.length - slice.length;
    $('#more').style.display = left > 0 ? '' : 'none';
    $('#moreInfo').textContent = list.length ? `Показано ${slice.length} из ${list.length}` : '';
    $('#moreBar').style.width = list.length ? (slice.length / list.length) * 100 + '%' : '0';
    renderTags();
  };

  const renderTags = () => {
    const tags = [];
    const g = { 'Женские': 'Для неё', 'Мужские': 'Для него', 'Унисекс': 'Унисекс' };
    if (state.q) tags.push(['q', `«${state.q}»`]);
    if (state.gender) tags.push(['gender', g[state.gender]]);
    if (state.brand) tags.push(['brand', state.brand]);
    if (state.volume) tags.push(['volume', state.volume + ' мл']);
    if (state.min) tags.push(['min', 'от ' + money(state.min)]);
    if (state.max) tags.push(['max', 'до ' + money(state.max)]);
    if (state.stock) tags.push(['stock', 'В наличии']);
    if (state.fav) tags.push(['fav', 'Избранное']);
    $('#activeTags').innerHTML = tags.map(([k, t]) => `<button type="button" class="tag" data-untag="${k}">${esc(t)} ${icon('close')}</button>`).join('');
  };

  const syncFilters = () => {
    $('#fSearch').value = state.q;
    $('#fMin').value = state.min;
    $('#fMax').value = state.max;
    $('#fStock').checked = state.stock;
    $('#fFav').checked = state.fav;
    $('#fSort').value = state.sort;
    $$('#fGender .chip').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.gender));
    $$('#fVolume .chip').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.volume));
    $$('#fBrand .f-opt').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.brand));
  };

  const setFilter = (patch, scroll) => {
    Object.assign(state, patch, { shown: PAGE });
    syncFilters();
    renderCatalog();
    if (scroll) $('#catalog').scrollIntoView({ behavior: 'smooth' });
  };

  const resetFilters = () => setFilter({ q: '', gender: '', volume: '', brand: '', min: '', max: '', stock: false, fav: false });

  const renderHits = () => {
    const hits = PRODUCTS.filter((p) => p.hit && p.inStock);
    $('#hits').innerHTML = hits.map(cardHtml).join('');
  };

  const rerenderCards = () => { renderHits(); renderCatalog(); };

  /* ---------- Search ---------- */
  const renderSearch = () => {
    const q = $('#searchInput').value.trim().toLowerCase();
    const box = $('#searchResults');
    if (q.length < 2) { box.innerHTML = ''; return; }
    const hits = PRODUCTS.filter((p) => (p.name + ' ' + p.brand).toLowerCase().includes(q)).slice(0, 9);
    box.innerHTML = hits.length ? hits.map((p) => `
      <a href="#" class="search-hit" data-view="${p.id}">
        <img src="${esc(p.image || PLACEHOLDER)}" alt="" loading="lazy">
        <div><b>${esc(p.brand)}</b><span>${esc(p.short)}</span></div>
      </a>`).join('') + (hits.length === 9 ? `<a href="#catalog" class="search-hit" data-search-all><div><b>Все результаты</b><span>Показать в каталоге</span></div></a>` : '')
      : '<p class="search-empty">Ничего не нашлось. Попробуйте другое написание.</p>';
  };

  /* ---------- Product modal ---------- */
  const openProduct = (id) => {
    const p = BY_ID.get(id);
    if (!p) return;
    const v = volOf(p);
    const price = volPrice(p, v);
    const old = price < (p.volumes[v] || 0) ? p.volumes[v] : (p.discount ? p.base[v] : 0);
    const vols = volBtns(p);
    const genders = { 'Женские': 'Для неё', 'Мужские': 'Для него', 'Унисекс': 'Унисекс' };
    $('#productBody').innerHTML = `
      <div class="pv">
        <div class="pv__media ${p.image ? '' : 'pv__media--empty'}"><img src="${esc(p.image || PLACEHOLDER)}" alt="${esc(p.name)}"></div>
        <div class="pv__body">
          <div class="pv__brand">${esc(p.brand)}</div>
          <h3 class="pv__name">${esc(p.short)}</h3>
          <div class="pv__meta"><span>${genders[p.gender] || p.gender}</span><span>${p.inStock ? 'В наличии' : 'Нет в наличии'}</span>${p.family ? `<span>${esc(p.family)}</span>` : ''}</div>
          <div class="vols">${vols}</div>
          ${p.inStock
            ? `<div class="pv__price">${money(price)}${old ? `<s>${money(old)}</s>` : ''}<small>за ${v} мл</small></div>
               <div class="pv__actions">
                 <button type="button" class="btn btn--primary" data-add="${p.id}">В корзину ${icon('bag')}</button>
                 <button type="button" class="btn btn--line" data-book="${p.id}">Бронь</button>
                 <button type="button" class="icon-btn ${state.favs.includes(p.id) ? 'is-active' : ''}" style="width:54px;height:54px" data-fav="${p.id}" aria-label="В избранное">${icon('heart')}</button>
               </div>`
            : `<div class="pv__price"><small style="margin:0">Сейчас нет в наличии</small></div>
               <div class="pv__actions"><button type="button" class="btn btn--primary" data-book="${p.id}">Забронировать</button></div>`}
          <div class="pv__note">
            <div>${icon('shield')} Декларация соответствия и «Честный ЗНАК»</div>
            <div>${icon('gift')} Подарочная упаковка, подарок 2 мл от ${money(GIFT_FROM)}</div>
            <div>${icon('fast')} Обычно отправляем в течение суток</div>
          </div>
        </div>
      </div>`;
    open('product');
  };

  /* ---------- Favorites ---------- */
  const toggleFav = (id) => {
    const i = state.favs.indexOf(id);
    if (i >= 0) state.favs.splice(i, 1); else { state.favs.push(id); toast('Добавлено в избранное'); }
    save('ps_favs', state.favs);
    rerenderCards();
    renderFavs();
    counters();
    if ($('#product').classList.contains('is-open')) openProduct(id);
  };

  const renderFavs = () => {
    const list = state.favs.map((id) => BY_ID.get(id)).filter(Boolean);
    $('#favQty').textContent = list.length || '';
    $('#favBody').innerHTML = list.length ? list.map((p) => `
      <div class="line-item">
        <img class="line-item__img" src="${esc(p.image || PLACEHOLDER)}" alt="" loading="lazy">
        <div>
          <div class="line-item__brand">${esc(p.brand)}</div>
          <div class="line-item__name"><a href="#" data-view="${p.id}">${esc(p.short)}</a></div>
          <div class="line-item__vol">${p.inStock ? 'от ' + money(p.min) : 'Нет в наличии'}</div>
        </div>
        <div class="line-item__right">
          <button type="button" class="remove" data-fav="${p.id}">Убрать</button>
          ${p.inStock ? `<button type="button" class="icon-btn icon-btn--primary" data-add="${p.id}" aria-label="В корзину">${icon('bag')}</button>` : ''}
        </div>
      </div>`).join('') : emptyBlock('heart', 'Здесь пока пусто', 'Нажмите на сердечко в карточке аромата, чтобы сохранить его.');
  };

  /* ---------- Cart ---------- */
  const users = () => load('ps_users', []);
  const saveUser = (user) => {
    const list = users();
    const i = list.findIndex((u) => u.email === user.email);
    if (i >= 0) list[i] = user; else list.push(user);
    save('ps_users', list);
    state.user = user;
    save('ps_user', { email: user.email });
  };
  const currentUser = () => state.user && users().find((u) => u.email === state.user.email);

  const promoOf = (code) => PROMOS.find((p) => p.code.toUpperCase() === String(code || '').trim().toUpperCase());

  const totals = () => {
    let sum = 0, qty = 0;
    state.cart.forEach((i) => { const p = BY_ID.get(i.id); sum += volPrice(p, i.volume) * i.qty; qty += i.qty; });
    const discount = qty >= BUNDLE_MIN ? Math.round(sum * BUNDLE_PCT / 100) : 0;
    const user = currentUser();
    const promo = promoOf(state.promo);
    let promoSum = 0;
    let promoError = '';
    if (state.promo) {
      if (!promo) promoError = 'Такого промокода нет';
      else if (promo.registered_only && !user) promoError = 'Промокод для зарегистрированных';
      else if (promo.first_order && user && user.orders.length) promoError = 'Промокод только на первый заказ';
      else if (promo.once && user && user.usedPromos.includes(promo.code)) promoError = 'Этот промокод уже использован';
      else promoSum = Math.round((sum - discount) * promo.percent / 100);
    }
    const after = sum - discount - promoSum;
    const maxPoints = user ? Math.min(user.points, Math.floor(after * LOYALTY_MAX / 100)) : 0;
    const points = state.usePoints ? maxPoints : 0;
    const total = after - points;
    const gift = after >= GIFT_FROM;
    if (!gift) state.giftId = 0;
    const giftProduct = gift && state.giftId ? BY_ID.get(state.giftId) : null;
    return { sum, qty, discount, promo, promoSum, promoError, points, maxPoints, total, gift, giftProduct, user };
  };

  const addToCart = (id, btn) => {
    const p = BY_ID.get(id);
    if (!p || !p.inStock) return;
    const volume = volOf(p);
    const line = state.cart.find((i) => i.id === id && i.volume === volume);
    if (line) line.qty += 1; else state.cart.push({ id, volume, qty: 1 });
    save('ps_cart', state.cart);
    counters();
    renderCart();
    goal('add_to_cart', { id, volume });
    toast(`${p.short}, ${volume} мл — в корзине`);
    if (btn && btn.classList.contains('icon-btn')) {
      btn.classList.add('is-done');
      btn.innerHTML = icon('check');
      setTimeout(() => { btn.classList.remove('is-done'); btn.innerHTML = icon('bag'); }, 1400);
    }
  };

  const renderCart = () => {
    const t = totals();
    $('#cartQty').textContent = t.qty || '';
    const body = $('#cartBody');
    const foot = $('#cartFoot');
    if (!state.cart.length) {
      state.checkout = false;
      body.innerHTML = emptyBlock('bag', 'Корзина пуста', 'Выберите аромат и объём в каталоге.', '<a href="#catalog" class="btn btn--primary btn--sm" data-close="cart">В каталог</a>');
      foot.innerHTML = '';
      return;
    }
    const goals = `
      <div class="goal">
        <div>
          <div class="goal__row"><span>Подарок 2 мл от ${money(GIFT_FROM)}</span>${t.gift ? `<button type="button" class="ok" data-gift-pick="1">${t.giftProduct ? 'Изменить' : 'Выбрать'}</button>` : `<b>ещё ${money(GIFT_FROM - (t.total + t.points))}</b>`}</div>
          <div class="bar"><i style="width:${Math.min(100, t.total / GIFT_FROM * 100)}%"></i></div>
        </div>
        <div>
          <div class="goal__row"><span>Скидка ${BUNDLE_PCT}% от ${BUNDLE_MIN} позиций</span>${t.discount ? '<b class="ok">Применена</b>' : `<b>ещё ${BUNDLE_MIN - t.qty} ${plural(BUNDLE_MIN - t.qty, 'позиция', 'позиции', 'позиций')}</b>`}</div>
          <div class="bar"><i style="width:${Math.min(100, t.qty / BUNDLE_MIN * 100)}%"></i></div>
        </div>
      </div>`;

    const totalsHtml = `
      <div class="totals">
        <div><span>Товары, ${t.qty} шт.</span><span>${money(t.sum)}</span></div>
        ${t.discount ? `<div class="discount"><span>Скидка ${BUNDLE_PCT}%</span><span>−${money(t.discount)}</span></div>` : ''}
        ${t.promoSum ? `<div class="discount"><span>Промокод ${esc(t.promo.code)}</span><span>−${money(t.promoSum)}</span></div>` : ''}
        ${t.points ? `<div class="discount"><span>Баллы</span><span>−${money(t.points)}</span></div>` : ''}
        ${t.gift ? `<div class="discount"><span>Парфюм 2 мл</span><span>${t.giftProduct ? esc(t.giftProduct.short) : 'выберите аромат'}</span></div>` : ''}
        <div class="grand"><span>Итого</span><span>${money(t.total)}</span></div>
        <small>Доставка оплачивается по тарифу службы. Скидка от ${BUNDLE_MIN} позиций применяется автоматически.</small>
      </div>`;

    if (state.checkout) {
      body.innerHTML = `
        <div class="checkout">
          <button type="button" class="back" data-checkout="0">${icon('left')} К корзине</button>
          <form class="form" id="orderForm" novalidate>
            <div class="seg" role="radiogroup" aria-label="Тип заказа">
              <label><input type="radio" name="order_mode" value="buy" checked><span>Купить</span></label>
              <label><input type="radio" name="order_mode" value="book"><span>Забронировать</span></label>
            </div>
            <h4>Контакты</h4>
            <div class="field"><label for="oName">Имя</label><input class="input" id="oName" name="name" autocomplete="name" required></div>
            <div class="field"><label for="oPhone">Телефон</label><input class="input" id="oPhone" name="phone" type="tel" autocomplete="tel" placeholder="+7" value="${esc(state.phone)}" required></div>
            <div class="field"><label for="oEmail">Почта</label><input class="input" id="oEmail" name="email" type="email" autocomplete="email" required></div>
            <h4>Доставка и оплата</h4>
            <div class="field"><label for="oDelivery">Служба доставки</label>
              <select class="input" id="oDelivery" name="delivery"><option>СДЭК</option><option>Почта России</option><option>Яндекс Доставка</option></select></div>
            <div class="field"><label for="oAddress">Город и адрес или пункт выдачи</label><input class="input" id="oAddress" name="address" autocomplete="street-address"></div>
            <div class="field"><label for="oPay">Оплата</label>
              <select class="input" id="oPay" name="payment"><option>СБП</option><option>Банковская карта</option></select></div>
            <div class="field"><label for="oPromo">Промокод</label>
              <div class="promo-field"><input class="input" id="oPromo" name="promo" value="${esc(state.promo)}" placeholder="WELCOME20" autocomplete="off"><button class="btn btn--line btn--sm" type="button" data-apply-promo="1">Применить</button></div>
              ${t.promoError ? `<small class="muted">${esc(t.promoError)}</small>` : ''}
            </div>
            ${t.user && t.maxPoints ? `<label class="points-line"><input type="checkbox" name="use_points" ${state.usePoints ? 'checked' : ''}> Списать ${t.maxPoints} баллов (до ${LOYALTY_MAX}% заказа)</label>` : `<p class="muted">Баллы копятся в личном кабинете: ${LOYALTY_PCT}% от чека.</p>`}
            <label class="consent"><input type="checkbox" name="consent" required><span>Согласен на обработку персональных данных в соответствии с <a href="/privacy.php" target="_blank">политикой конфиденциальности</a></span></label>
          </form>
        </div>`;
      foot.innerHTML = totalsHtml + `<button type="submit" form="orderForm" class="btn btn--primary btn--block">Подтвердить заказ</button>`;
      return;
    }

    if (state.pickingGift && t.gift) {
      const q = state.giftQuery.toLowerCase();
      const list = PRODUCTS.filter((p) => p.inStock && (!q || (p.name + ' ' + p.brand).toLowerCase().includes(q))).slice(0, 40);
      body.innerHTML = `<button type="button" class="back" data-gift-pick="0">${icon('left')} К корзине</button>
        <p>Выберите аромат для подарка 2 мл.</p>
        <div class="gift-pick"><input class="input" id="giftSearch" placeholder="Бренд или название" value="${esc(state.giftQuery)}"></div>
        ${list.map((p) => `<button type="button" class="gift-hit" data-gift="${p.id}"><img src="${esc(p.image || PLACEHOLDER)}" alt=""><span><b>${esc(p.brand)}</b><br>${esc(p.short)}</span></button>`).join('')}`;
      foot.innerHTML = '';
      return;
    }

    body.innerHTML = goals + state.cart.map((i, idx) => {
      const p = BY_ID.get(i.id);
      return `
        <div class="line-item">
          <img class="line-item__img" src="${esc(p.image || PLACEHOLDER)}" alt="" loading="lazy">
          <div>
            <div class="line-item__brand">${esc(p.brand)}</div>
            <div class="line-item__name">${esc(p.short)}</div>
            <div class="line-item__vol">${i.volume} мл · ${money(volPrice(p, i.volume))}</div>
          </div>
          <div class="line-item__right">
            <div class="line-item__sum">${money(volPrice(p, i.volume) * i.qty)}</div>
            <div class="qty"><button type="button" data-qty="-1" data-idx="${idx}" aria-label="Меньше">−</button><span>${i.qty}</span><button type="button" data-qty="1" data-idx="${idx}" aria-label="Больше">+</button></div>
            <button type="button" class="remove" data-remove="${idx}">Удалить</button>
          </div>
        </div>`;
    }).join('') + (t.gift ? `<button type="button" class="gift-line" data-gift-pick="1">${icon('gift')}<span>${t.giftProduct ? `${esc(t.giftProduct.brand)} ${esc(t.giftProduct.short)}<small>Подарок 2 мл · нажмите, чтобы изменить</small>` : 'Выбрать аромат в подарок, 2 мл'}</span></button>` : '');
    foot.innerHTML = totalsHtml + `<button type="button" class="btn btn--primary btn--block" data-checkout="1">Оформить заказ ${icon('arrow')}</button>`;
  };

  const submitOrder = async (form) => {
    const fd = new FormData(form);
    const err = validate(fd);
    if (err) return toast(err);
    const btn = $('#cartFoot .btn');
    const promoField = $('#oPromo');
    if (promoField) { state.promo = promoField.value.trim().toUpperCase(); save('ps_promo', state.promo); }
    const pointsBox = form.querySelector('[name=use_points]');
    state.usePoints = !!(pointsBox && pointsBox.checked);
    const t = totals();
    if (t.promoError) return toast(t.promoError);
    if (t.gift && !t.giftProduct) return toast('Выберите аромат для подарка 2 мл');
    btn.disabled = true;
    const payload = Object.fromEntries(fd);
    payload.consent = fd.get('consent') === 'on';
    payload.items = state.cart;
    payload.promo_code = t.promoSum ? t.promo.code : '';
    payload.points_spend = t.points;
    payload.gift_id = t.giftProduct ? t.giftProduct.id : 0;
    const res = await post('create_order', payload);
    btn.disabled = false;
    if (!res.ok) return toast(res.error || 'Не удалось отправить заказ');
    state.phone = payload.phone;
    save('ps_phone', state.phone);
    state.orders.unshift(res.order);
    save('ps_orders', state.orders);
    const user = currentUser();
    if (user) {
      user.orders.push(res.order.id);
      user.points = Math.max(0, user.points - t.points) + Math.round(t.total * LOYALTY_PCT / 100);
      if (t.promoSum) user.usedPromos.push(t.promo.code);
      saveUser(user);
    }
    state.cart = [];
    state.checkout = false;
    state.giftId = 0;
    state.promo = '';
    state.usePoints = false;
    save('ps_cart', state.cart);
    save('ps_gift', 0);
    save('ps_promo', '');
    note(`${res.order.type === 'booking' ? 'Бронь' : 'Заказ'} ${res.order.id} принят`, res.message);
    goal(res.order.type === 'booking' ? 'booking_submit' : 'order_submit', { order_id: res.order.id, total: res.order.total });
    counters();
    renderCart();
    close('cart');
    toast(res.message);
  };

  /* ---------- Orders ---------- */
  const STATUS = { active: 'В работе', completed: 'Завершён' };

  const renderOrders = () => {
    const list = state.orders.filter((o) => (state.ordersTab === 'active' ? o.status !== 'completed' : o.status === 'completed'));
    $$('#orderTabs button').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.ordersTab));
    const items = list.map((o) => `
      <div class="order">
        <div class="order__top">
          <div><div class="order__id">${o.type === 'request' ? 'Заявка' : o.type === 'booking' ? 'Бронь' : 'Заказ'} ${esc(o.id)}</div><div class="order__date">${esc(o.created_at)}</div></div>
          <span class="status ${o.status === 'completed' ? 'status--done' : ''}">${STATUS[o.status] || o.status}</span>
        </div>
        ${o.items && o.items.length ? `<div class="order__items">${o.items.map((i) => `${esc(i.name)}, ${esc(i.volume)} мл × ${i.qty}`).join('<br>')}</div>` : `<div class="order__items">${esc(o.comment)}</div>`}
        ${o.total ? `<div class="order__sum">${money(o.total)}${o.gift_2ml ? ' · подарок 2 мл' + (o.gift_name ? ': ' + esc(o.gift_name) : '') : ''}</div>` : ''}
      </div>`).join('');
    $('#ordersList').innerHTML = (items || emptyBlock('box', state.ordersTab === 'active' ? 'Активных заказов нет' : 'Завершённых заказов нет', 'Здесь появятся заказы, оформленные на этом устройстве.')) + `
      <form class="lookup" id="lookupForm">
        <p>Оформляли заказ на другом устройстве? Укажите телефон и номер заказа.</p>
        <div class="form-row"><input class="input" name="id" placeholder="Номер, например PS-261016-0001" required><button class="btn btn--line btn--sm" style="height:52px">Найти</button></div>
        <input class="input" name="phone" type="tel" placeholder="Телефон" value="${esc(state.phone)}" required style="margin-top:10px">
      </form>`;
  };

  const refreshOrders = async (extraId, phone) => {
    const ph = phone || state.phone;
    const ids = state.orders.map((o) => o.id).concat(extraId ? [extraId] : []);
    if (!ph || !ids.length) return;
    const res = await post('my_orders', { phone: ph, ids });
    if (!res.ok) return;
    const before = new Map(state.orders.map((o) => [o.id, o.status]));
    res.orders.forEach((o) => {
      const prev = before.get(o.id);
      if (prev && prev !== o.status) note(`${o.id}: ${STATUS[o.status] || o.status}`, o.status === 'completed' ? 'Заказ завершён. Спасибо за покупку!' : 'Статус заказа обновлён.');
      const i = state.orders.findIndex((x) => x.id === o.id);
      if (i >= 0) state.orders[i] = o; else state.orders.unshift(o);
    });
    if (extraId && !res.orders.some((o) => o.id === extraId)) toast('Заказ с таким номером и телефоном не найден');
    save('ps_orders', state.orders);
    counters();
    renderOrders();
  };

  /* ---------- Notifications ---------- */
  const note = (title, text) => {
    state.notes.unshift({ title, text, time: new Date().toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }), unread: true });
    state.notes = state.notes.slice(0, 30);
    save('ps_notes', state.notes);
    counters();
  };

  const renderNotes = () => {
    $('#notesList').innerHTML = state.notes.length ? state.notes.map((n) => `
      <div class="note ${n.unread ? 'is-new' : ''}"><div class="note__top">${esc(n.title)}<span>${esc(n.time)}</span></div><p>${esc(n.text)}</p></div>`).join('')
      : emptyBlock('bell', 'Уведомлений нет', 'Здесь появятся новости о ваших заказах.');
    state.notes.forEach((n) => { n.unread = false; });
    save('ps_notes', state.notes);
    counters();
  };

  /* ---------- UI helpers ---------- */
  const counters = () => {
    const set = (k, n) => { const el = $(`[data-count="${k}"]`); if (el) el.textContent = n > 0 ? n : ''; };
    set('cart', totals().qty);
    set('favs', state.favs.length);
    set('orders', state.orders.filter((o) => o.status !== 'completed').length);
    set('notes', state.notes.filter((n) => n.unread).length);
  };

  const emptyBlock = (ic, title, text, extra = '') => `<div class="drawer-empty">${icon(ic)}<h3>${title}</h3><p>${text}</p>${extra}</div>`;
  const plural = (n, one, few, many) => { const m10 = n % 10, m100 = n % 100; return m10 === 1 && m100 !== 11 ? one : m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20) ? few : many; };

  const toast = (msg) => {
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = icon('check') + `<span>${esc(msg)}</span>`;
    $('#toasts').appendChild(el);
    setTimeout(() => el.remove(), 3600);
  };

  const validate = (fd) => {
    if (!String(fd.get('name') || '').trim()) return 'Укажите имя';
    if (String(fd.get('phone') || '').replace(/\D/g, '').length < 10) return 'Проверьте телефон';
    if (!/^\S+@\S+\.\S+$/.test(String(fd.get('email') || ''))) return 'Проверьте почту';
    if (fd.get('consent') !== 'on') return 'Нужно согласие на обработку персональных данных';
    return '';
  };

  const post = async (action, data) => {
    if (window.PS_API) return window.PS_API(action, data);
    try {
      const r = await fetch('/api.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      return await r.json();
    } catch (e) {
      return { ok: false, error: 'Нет связи с сервером. Попробуйте ещё раз.' };
    }
  };

  const PANELS = ['cart', 'favs', 'orders', 'notes', 'account', 'product', 'filters'];
  const open = (id) => {
    PANELS.forEach((x) => x !== id && $('#' + x)?.classList.remove('is-open'));
    if (id === 'cart') renderCart();
    if (id === 'favs') renderFavs();
    if (id === 'orders') { renderOrders(); refreshOrders(); }
    if (id === 'notes') renderNotes();
    if (id === 'account') renderAccount();
    $('#' + id).classList.add('is-open');
    $('#' + id).setAttribute('aria-hidden', 'false');
    $('#overlay').classList.add('is-open');
    document.body.classList.add('is-locked');
  };
  const close = (id) => {
    const el = $('#' + id);
    if (!el) return;
    el.classList.remove('is-open');
    el.setAttribute('aria-hidden', 'true');
    if (!PANELS.some((x) => $('#' + x)?.classList.contains('is-open'))) {
      $('#overlay').classList.remove('is-open');
      document.body.classList.remove('is-locked');
    }
  };
  const closeAll = () => PANELS.forEach(close);

  const toggleSearch = (force) => {
    const panel = $('#search');
    const show = force ?? !panel.classList.contains('is-open');
    panel.classList.toggle('is-open', show);
    if (show) setTimeout(() => $('#searchInput').focus(), 50);
  };

  const book = (id) => {
    const p = BY_ID.get(id);
    closeAll();
    $('#lType').value = p.inStock ? 'Забронировать аромат' : 'Сообщить о поступлении';
    $('#lPerfume').value = `${p.name}${p.inStock ? ', ' + volOf(p) + ' мл' : ''}`;
    $('#request').scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => $('#lName').focus({ preventScroll: true }), 700);
  };

  /* ---------- Account, gift, questions ---------- */
  const hashPass = (s) => {
    let h = 5381;
    const str = 'ps:' + s;
    for (let i = 0; i < str.length; i++) h = ((h << 5) + h) ^ str.charCodeAt(i);
    return (h >>> 0).toString(16);
  };

  const renderAccount = () => {
    const user = currentUser();
    const box = $('#accountBody');
    if (!user) {
      box.innerHTML = `
        <form class="form" id="authForm">
          <div class="seg"><label><input type="radio" name="mode" value="login" checked><span>Войти</span></label><label><input type="radio" name="mode" value="register"><span>Регистрация</span></label></div>
          <div class="field reg" hidden><label>Имя</label><input class="input" name="name"></div>
          <div class="field"><label>Почта</label><input class="input" name="email" type="email" required></div>
          <div class="field reg" hidden><label>Телефон</label><input class="input" name="phone" type="tel"></div>
          <div class="field"><label>Пароль</label><input class="input" name="password" type="password" required></div>
          <button class="btn btn--primary btn--block" type="submit">Продолжить</button>
        </form>`;
      box.querySelector('#authForm').addEventListener('change', (e) => {
        if (e.target.name !== 'mode') return;
        box.querySelectorAll('.reg').forEach((f) => { f.hidden = e.target.value !== 'register'; });
      });
      return;
    }
    const mine = state.orders.filter((o) => user.orders.includes(o.id));
    box.innerHTML = `
      <p>${esc(user.name)} · ${esc(user.email)}</p>
      <div class="totals"><div><span>Баллы</span><b>${user.points}</b></div><small>1% от каждого чека. 1 балл = 1 ₽, можно оплатить до ${LOYALTY_MAX}% заказа.</small></div>
      <h3 style="margin:22px 0 8px;font-family:var(--serif);font-size:26px">Заказы</h3>
      ${mine.length ? mine.map((o) => `<div class="order"><div class="order__id">${esc(o.id)}</div><div class="order__sum">${o.total ? money(o.total) : ''}</div></div>`).join('') : '<p class="muted">Покупок пока нет.</p>'}
      <h3 style="margin:22px 0 8px;font-family:var(--serif);font-size:26px">Избранное</h3>
      <p>${state.favs.length ? state.favs.length + ' ароматов' : 'Пока пусто'}</p>
      <p style="margin-top:8px">В корзине: ${totals().qty} шт.</p>
      <button class="btn btn--line" type="button" data-logout="1" style="margin-top:18px">Выйти</button>`;
  };

  const answerQuestion = (q) => {
    const text = q.toLowerCase();
    const hard = /доставк|оплат|возврат|чек|жалоб|оригинал|документ|менеджер|скидк|промокод|балл/.test(text);
    const scored = PRODUCTS.map((p) => {
      const hay = (p.brand + ' ' + p.short + ' ' + p.name).toLowerCase();
      const words = text.split(/[^a-zа-я0-9]+/i).filter((w) => w.length > 3);
      const score = words.reduce((n, w) => n + (hay.includes(w) ? 1 : 0), 0);
      return { p, score };
    }).filter((x) => x.score >= 2).sort((a, b) => b.score - a.score).slice(0, 3);
    if (!hard && scored.length) {
      return scored.map(({ p }) => `${p.brand} ${p.short}: ${p.inStock ? 'в наличии, от ' + money(p.min) : 'сейчас нет'}. <a href="#catalog" data-view="${p.id}">Открыть</a>`).join('<br><br>');
    }
    post('create_request', { name: 'Вопрос с сайта', phone: state.phone || '+70000000000', email: (currentUser() && currentUser().email) || 'question@perfume-space.ru', request_type: 'Вопрос', perfume_name: q, consent: true });
    return 'Это лучше ответит менеджер. Вопрос передан, ответим в рабочее время.';
  };

  const askLog = (text, who) => {
    const log = $('#askLog');
    const el = document.createElement('div');
    el.className = 'ask-msg ask-msg--' + who;
    el.innerHTML = text;
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  };

  /* ---------- Events ---------- */
  document.addEventListener('click', (e) => {
    const t = e.target.closest('button, a, [data-view]');
    if (!t) return;
    const d = t.dataset;

    if (d.vol) {
      const id = Number(d.id);
      state.picked[id] = d.vol;
      rerenderCards();
      if ($('#product').classList.contains('is-open')) openProduct(id);
      return;
    }
    if (d.add) { addToCart(Number(d.add), t); return; }
    if (d.book) { book(Number(d.book)); return; }
    if (d.fav) { toggleFav(Number(d.fav)); return; }
    if (d.view) { e.preventDefault(); toggleSearch(false); openProduct(Number(d.view)); return; }
    if (d.open) { e.preventDefault(); if (d.open === 'search') toggleSearch(); else open(d.open); return; }
    if (d.close) { if (d.close === 'search') toggleSearch(false); else close(d.close); return; }
    if (d.gender !== undefined && t.matches('.cat, .footer a')) { e.preventDefault(); setFilter({ gender: d.gender }, true); return; }
    if (d.brand) { setFilter({ brand: d.brand }, true); return; }
    if (d.untag) { const k = d.untag; setFilter({ [k]: typeof state[k] === 'boolean' ? false : '' }); return; }
    if (d.reset !== undefined) { resetFilters(); return; }
    if (d.checkout) { state.checkout = d.checkout === '1'; renderCart(); return; }
    if (d.giftPick !== undefined) { state.pickingGift = d.giftPick === '1'; renderCart(); return; }
    if (d.gift) { state.giftId = Number(d.gift); state.pickingGift = false; save('ps_gift', state.giftId); renderCart(); return; }
    if (d.applyPromo !== undefined) { state.promo = ($('#oPromo').value || '').trim().toUpperCase(); save('ps_promo', state.promo); renderCart(); return; }
    if (d.copy) { navigator.clipboard && navigator.clipboard.writeText(d.copy); state.promo = d.copy; save('ps_promo', state.promo); toast('Промокод ' + d.copy + ' скопирован'); return; }
    if (d.ask !== undefined) { $('#askBox').hidden = d.ask !== '1'; if (d.ask === '1' && !$('#askLog').childElementCount) askLog('Спросите про аромат — подскажу по каталогу. Сложный вопрос передам менеджеру.', 'bot'); return; }
    if (d.logout) { state.user = null; save('ps_user', null); renderAccount(); return; }
    if (d.qty) {
      const line = state.cart[Number(d.idx)];
      line.qty += Number(d.qty);
      if (line.qty <= 0) state.cart.splice(Number(d.idx), 1);
      save('ps_cart', state.cart); counters(); renderCart(); return;
    }
    if (d.remove) { state.cart.splice(Number(d.remove), 1); save('ps_cart', state.cart); counters(); renderCart(); return; }
    if (d.rail) { const r = $('#hits'); r.scrollBy({ left: Number(d.rail) * r.clientWidth * 0.75, behavior: 'smooth' }); return; }
    if (d.searchAll !== undefined) { e.preventDefault(); setFilter({ q: $('#searchInput').value }, true); toggleSearch(false); return; }
    if (d.goal) goal(d.goal);
    if (t.closest('#nav') ) $('#nav').classList.remove('is-open');
  });

  $('#overlay').addEventListener('click', closeAll);
  $('#product').addEventListener('click', (e) => { if (e.target.id === 'product') close('product'); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeAll(); toggleSearch(false); } });
  $('#burger').addEventListener('click', () => $('#nav').classList.toggle('is-open'));

  $('#searchInput').addEventListener('input', renderSearch);
  $('#searchInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') { setFilter({ q: e.target.value }, true); toggleSearch(false); } });

  let typing;
  $('#fSearch').addEventListener('input', (e) => { clearTimeout(typing); typing = setTimeout(() => setFilter({ q: e.target.value }), 180); });
  $('#fMin').addEventListener('change', (e) => setFilter({ min: e.target.value }));
  $('#fMax').addEventListener('change', (e) => setFilter({ max: e.target.value }));
  $('#fStock').addEventListener('change', (e) => setFilter({ stock: e.target.checked }));
  $('#fFav').addEventListener('change', (e) => setFilter({ fav: e.target.checked }));
  $('#fSort').addEventListener('change', (e) => setFilter({ sort: e.target.value }));
  $('#fGender').addEventListener('click', (e) => { const b = e.target.closest('.chip'); if (b) setFilter({ gender: b.dataset.v }); });
  $('#fVolume').addEventListener('click', (e) => { const b = e.target.closest('.chip'); if (b) setFilter({ volume: b.dataset.v }); });
  $('#fBrand').addEventListener('click', (e) => { const b = e.target.closest('.f-opt'); if (b) setFilter({ brand: b.dataset.v }); });
  $('#fReset').addEventListener('click', resetFilters);
  $('#more').addEventListener('click', () => { state.shown += PAGE; renderCatalog(); });
  document.addEventListener('input', (e) => {
    if (e.target.id !== 'giftSearch') return;
    state.giftQuery = e.target.value;
    const pos = e.target.selectionStart;
    renderCart();
    const el = $('#giftSearch');
    if (el) { el.focus(); el.setSelectionRange(pos, pos); }
  });

  $('#orderTabs').addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) { state.ordersTab = b.dataset.v; renderOrders(); } });

  $('#abc').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    $$('#abc button').forEach((x) => x.classList.toggle('is-active', x === b));
    $$('.brand-group').forEach((g) => { g.style.display = b.dataset.letter === 'ALL' || g.dataset.group === b.dataset.letter ? '' : 'none'; });
  });

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.id === 'orderForm') { e.preventDefault(); submitOrder(form); }
    if (form.id === 'lookupForm') {
      e.preventDefault();
      const fd = new FormData(form);
      state.phone = String(fd.get('phone'));
      save('ps_phone', state.phone);
      refreshOrders(String(fd.get('id')).trim().toUpperCase(), state.phone);
    }
    if (form.id === 'authForm') {
      e.preventDefault();
      const fd = new FormData(form);
      const email = String(fd.get('email') || '').trim().toLowerCase();
      const pass = hashPass(String(fd.get('password') || ''));
      const list = users();
      if (fd.get('mode') === 'register') {
        if (!String(fd.get('name') || '').trim()) return toast('Укажите имя');
        if (list.some((u) => u.email === email)) return toast('Такая почта уже зарегистрирована');
        saveUser({ email, name: String(fd.get('name')).trim(), phone: String(fd.get('phone') || ''), pass, points: 0, orders: [], usedPromos: [] });
        toast('Кабинет создан');
      } else {
        const found = list.find((u) => u.email === email && u.pass === pass);
        if (!found) return toast('Неверная почта или пароль');
        state.user = { email }; save('ps_user', state.user);
      }
      renderAccount();
    }
    if (form.id === 'reviewForm') {
      e.preventDefault();
      const fd = new FormData(form);
      if (!String(fd.get('name') || '').trim()) return toast('Укажите имя');
      if (String(fd.get('text') || '').trim().length < 10) return toast('Напишите отзыв чуть подробнее');
      if (fd.get('consent') !== 'on') return toast('Нужно согласие на публикацию');
      const res = await post('create_review', { name: fd.get('name'), perfume: fd.get('perfume'), text: fd.get('text'), rating: fd.get('rating'), consent: true });
      if (!res.ok) return toast(res.error || 'Не удалось отправить отзыв');
      form.reset();
      toast(res.message);
    }
    if (form.id === 'askForm') {
      e.preventDefault();
      const q = String(new FormData(form).get('q') || '').trim();
      if (!q) return;
      askLog(esc(q), 'user');
      form.reset();
      askLog(answerQuestion(q), 'bot');
    }
    if (form.id === 'leadForm') {
      e.preventDefault();
      const fd = new FormData(form);
      const err = validate(fd);
      if (err) return toast(err);
      const payload = Object.fromEntries(fd);
      payload.consent = true;
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      const res = await post('create_request', payload);
      btn.disabled = false;
      if (!res.ok) return toast(res.error || 'Не удалось отправить заявку');
      state.phone = payload.phone;
      save('ps_phone', state.phone);
      state.orders.unshift(res.order);
      save('ps_orders', state.orders);
      note(`Заявка ${res.order.id}`, res.message);
      goal('lead_submit');
      counters();
      form.reset();
      toast(res.message);
    }
  });

  if (!localStorage.getItem('ps_cookie')) $('#cookie').classList.add('is-open');
  $('#cookieOk').addEventListener('click', () => { localStorage.setItem('ps_cookie', '1'); $('#cookie').classList.remove('is-open'); });

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } }), { rootMargin: '0px 0px -8% 0px' });
    $$('.reveal').forEach((el) => io.observe(el));
  } else {
    $$('.reveal').forEach((el) => el.classList.add('is-in'));
  }

  const params = new URLSearchParams(location.search);
  if (params.get('gender')) state.gender = params.get('gender');
  if (params.get('brand')) state.brand = params.get('brand');
  if (params.get('q')) state.q = params.get('q');

  syncFilters();
  renderHits();
  renderCatalog();
  counters();
  refreshOrders();
  if ((params.get('gender') || params.get('brand') || params.get('q')) && !location.hash) {
    requestAnimationFrame(() => $('#catalog').scrollIntoView({ behavior: 'auto' }));
  }
})();
