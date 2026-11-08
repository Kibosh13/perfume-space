(function () {
  'use strict';

  const CFG = window.PS || {};
  const BASE = CFG.base || '';
  const ROWS = window.PS_PRODUCTS || [];
  const PRODUCTS = ROWS.map((r) => ({
    id: r[0], brand: r[1], short: r[2], name: r[3], gender: r[4],
    volumes: { 5: r[5], 10: r[6], 15: r[7] },
    inStock: !!r[8], hit: !!r[9], image: r[10], family: r[11], discount: r[12] || 0, newness: r[13] || 0,
    slug: r[14], accords: r[15] || [], seasons: r[16] || [], rating: r[17] || 0, reviews: r[18] || 0,
    pop: r[19] || 0, conc: r[20] || '', isNew: !!r[21],
  })).map((p) => {
    p.base = { ...p.volumes };
    if (p.discount) Object.keys(p.volumes).forEach((v) => { if (p.volumes[v]) p.volumes[v] = Math.round(p.volumes[v] * (100 - p.discount) / 100); });
    const prices = ['5', '10', '15'].filter((v) => p.volumes[v]).map((v) => volPrice(p, v));
    p.min = prices.length ? Math.min(...prices) : 0;
    p.url = `${BASE}/aromat/${encodeURIComponent(p.slug)}/`;
    return p;
  });
  const BY_ID = new Map(PRODUCTS.map((p) => [p.id, p]));
  const GIFT_FROM = CFG.giftFrom || 8000;
  const BUNDLE_MIN = CFG.bundleMin || 5;
  const BUNDLE_PCT = CFG.bundlePct || 10;
  const LOYALTY_PCT = CFG.loyaltyPct || 1;
  const LOYALTY_MAX = CFG.loyaltyMax || 50;
  const PAGE = 24;
  const PLACEHOLDER = BASE + '/assets/img/atomizer.webp';
  const SCOPE = Array.isArray(CFG.scope) ? new Set(CFG.scope) : null;
  const GENDERS = { 'Женские': 'Женский', 'Мужские': 'Мужской', 'Унисекс': 'Унисекс' };

  function volPrice(p, vol) {
    const base = p.volumes[vol] || 0;
    const off = ({ 10: 3, 15: 7 })[vol] || 0;
    return off ? Math.round(base * (100 - off) / 100) : base;
  }

  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const load = (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } };
  const save = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
  const money = (n) => Number(n || 0).toLocaleString('ru-RU') + ' ₽';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (id) => `<svg class="icon"><use href="#i-${id}"/></svg>`;
  const goal = (name, params) => { if (window.ym && window.PS_METRIKA_ID) window.ym(window.PS_METRIKA_ID, 'reachGoal', name, params || {}); };
  const plural = (n, one, few, many) => { const m10 = n % 10, m100 = n % 100; return m10 === 1 && m100 !== 11 ? one : m10 >= 2 && m10 <= 4 && (m100 < 10 || m100 >= 20) ? few : many; };
  const brandUrl = (b) => `${BASE}/brand/${encodeURIComponent((CFG.brandSlugs || {})[b] || '')}/`;

  /* ---------- Умный поиск: транслит, варианты написания, опечатки ---------- */
  const CYR = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sh', ъ: '', ы: 'i', ь: '', э: 'e', ю: 'yu', я: 'ya' };
  const phon = (word) => word
    .replace(/sch/g, 'sh').replace(/ch/g, 'sh').replace(/zh/g, 'j').replace(/dj|dzh/g, 'j').replace(/g(?=[eiy])/g, 'j')
    .replace(/ph/g, 'f').replace(/th/g, 't').replace(/ck/g, 'k').replace(/c(?=[eiy])/g, 's').replace(/c/g, 'k')
    .replace(/q/g, 'k').replace(/x/g, 'ks').replace(/w/g, 'v').replace(/tz|ts/g, 's').replace(/z/g, 's')
    .replace(/ou|oo/g, 'u').replace(/ee|ea/g, 'i').replace(/y/g, 'i').replace(/h/g, '')
    .replace(/(.)\1+/g, '$1');
  const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[а-яё]/g, (c) => CYR[c] ?? c).replace(/[^a-z0-9]+/g, ' ').trim();
  const tokens = (s) => norm(s).split(' ').filter(Boolean).map((t) => (/^\d+$/.test(t) ? t : phon(t))).filter(Boolean);
  const lev = (a, b, max) => {
    if (Math.abs(a.length - b.length) > max) return max + 1;
    let prev = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
      const cur = [i];
      let best = i;
      for (let j = 1; j <= b.length; j++) {
        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        best = Math.min(best, cur[j]);
      }
      if (best > max) return max + 1;
      prev = cur;
    }
    return prev[b.length];
  };
  PRODUCTS.forEach((p) => {
    const aliases = (CFG.aliases || {})[p.brand] || '';
    p.tok = [...new Set(tokens(`${p.brand} ${p.name} ${aliases}`))];
    p.joined = p.tok.join('');
  });
  const tokenScore = (q, list, joined) => {
    let best = 0;
    for (const t of list) {
      if (t === q) return 4;
      if (t.startsWith(q)) best = Math.max(best, 3);
      else if (q.length >= 3 && t.includes(q)) best = Math.max(best, 2);
      else if (q.length >= 4) {
        const max = q.length >= 7 ? 2 : 1;
        if (lev(q, t.slice(0, q.length), max) <= max || lev(q, t, max) <= max) best = Math.max(best, 1);
      }
    }
    if (!best && q.length >= 4 && joined.includes(q)) best = 2; // «баккарат» без пробелов, «томфорд»
    return best;
  };
  const search = (query, list = PRODUCTS) => {
    const qt = tokens(query);
    if (!qt.length) return list.slice();
    const res = [];
    for (const p of list) {
      let sum = 0;
      let ok = true;
      for (const q of qt) {
        const s = tokenScore(q, p.tok, p.joined);
        if (!s) { ok = false; break; }
        sum += s;
      }
      if (ok) res.push([sum + (p.inStock ? 0.5 : 0) + Math.min(p.pop, 50) / 100, p]);
    }
    return res.sort((a, b) => b[0] - a[0]).map((x) => x[1]);
  };

  const state = {
    q: '', gender: '', volume: '', brand: '', min: '', max: '', stock: false, fav: false, season: '', accords: [],
    sort: CFG.defaultSort || 'popular',
    shown: PAGE,
    picked: {},
    cart: load('ps_cart', []).filter((i) => BY_ID.has(i.id)),
    favs: load('ps_favs', []).filter((id) => BY_ID.has(id)),
    orders: load('ps_orders', []),
    notes: load('ps_notes', []),
    phone: load('ps_phone', ''),
    ordersTab: 'active',
    step: 'cart',
    form: load('ps_checkout', {}),
    giftId: load('ps_gift', 0),
    pickingGift: false,
    giftQuery: '',
    promo: load('ps_promo2', null),
    usePoints: false,
    user: load('ps_user', null),
    lastOrder: null,
  };

  /* ---------- Карточки ---------- */
  const volOf = (p) => {
    const v = state.picked[p.id];
    if (v && p.volumes[v]) return v;
    if (state.volume && p.volumes[state.volume]) return state.volume;
    return ['5', '10', '15'].find((x) => p.volumes[x]) || '5';
  };

  const volBtns = (p) => {
    const v = volOf(p);
    return ['5', '10', '15'].map((x) => {
      const off = ({ 10: 3, 15: 7 })[x] || 0;
      return `<button type="button" class="vol ${x === v && p.volumes[x] ? 'is-active' : ''}" data-vol="${x}" data-id="${p.id}" ${p.volumes[x] && p.inStock ? '' : 'disabled'}>${x} мл${off && p.volumes[x] ? `<span class="vol__off">−${off}%</span>` : ''}</button>`;
    }).join('');
  };

  const starsHtml = (r) => `<span class="stars">${[1, 2, 3, 4, 5].map((i) => `<svg class="icon ${i <= Math.round(r) ? 'is-on' : ''}"><use href="#i-star"/></svg>`).join('')}</span>`;

  const media = (p) => (p.image
    ? `<img src="${esc(BASE + p.image)}" alt="${esc(p.name)} — распив" loading="lazy" width="480" height="480">`
    : `<img src="${PLACEHOLDER}" alt="" loading="lazy" width="520" height="520"><span class="card__ph-brand">${esc(p.brand)}</span>`);

  const cardHtml = (p) => {
    const v = volOf(p);
    const price = volPrice(p, v);
    const old = price < (p.volumes[v] || 0) ? p.volumes[v] : (p.discount ? p.base[v] : 0);
    const fav = state.favs.includes(p.id);
    const badges = [p.discount && p.inStock ? `<span class="badge badge--sale">−${p.discount}%</span>` : '', p.hit && p.inStock ? '<span class="badge badge--hit">Хит</span>' : '', !p.inStock ? '<span class="badge badge--out">Нет в наличии</span>' : ''].join('');
    return `
      <article class="card ${p.inStock ? '' : 'card__out'}" data-card="${p.id}">
        <a href="${p.url}" class="card__media ${p.image ? '' : 'card__media--empty'}">
          <div class="card__badges">${badges}</div>
          ${media(p)}
        </a>
        <button type="button" class="card__fav ${fav ? 'is-active' : ''}" data-fav="${p.id}" aria-label="${fav ? 'Убрать из избранного' : 'В избранное'}">${icon('heart')}</button>
        <a href="${brandUrl(p.brand)}" class="card__brand">${esc(p.brand)}</a>
        <h3 class="card__name"><a href="${p.url}">${esc(p.short)}</a></h3>
        ${p.reviews ? `<div class="card__rating">${starsHtml(p.rating)}<span>${p.reviews}</span></div>` : ''}
        <div class="vols">${volBtns(p)}</div>
        <div class="card__foot">
          ${p.inStock
            ? `<div class="price">${money(price)}${old ? `<s>${money(old)}</s>` : ''}<small>за ${v} мл</small></div>
               <div class="card__actions">
                 <button type="button" class="icon-btn icon-btn--primary" data-add="${p.id}" aria-label="В корзину" title="В корзину">${icon('bag')}</button>
               </div>`
            : `<div class="price"><small>Сейчас нет в наличии</small></div>
               <button type="button" class="card__notify" data-book="${p.id}">Забронировать</button>`}
        </div>
      </article>`;
  };

  /** Перерисовать карточку на месте (смена объёма) */
  const refreshCard = (id) => {
    const p = BY_ID.get(id);
    $$(`[data-card="${id}"]`).forEach((el) => {
      const tmp = document.createElement('div');
      tmp.innerHTML = cardHtml(p).trim();
      el.replaceWith(tmp.firstChild);
    });
  };

  const syncFavButtons = () => {
    $$('[data-fav]').forEach((b) => {
      const on = state.favs.includes(Number(b.dataset.fav));
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-label', on ? 'Убрать из избранного' : 'В избранное');
    });
  };

  /* ---------- Каталог ---------- */
  const scopeList = () => (SCOPE ? PRODUCTS.filter((p) => SCOPE.has(p.id)) : PRODUCTS);

  const filtered = () => {
    const min = state.min === '' ? null : Number(state.min);
    const max = state.max === '' ? null : Number(state.max);
    let list = scopeList().filter((p) => {
      if (state.gender && p.gender !== state.gender) return false;
      if (state.brand && p.brand !== state.brand) return false;
      if (state.volume && !p.volumes[state.volume]) return false;
      if (state.stock && !p.inStock) return false;
      if (state.fav && !state.favs.includes(p.id)) return false;
      if (state.season && !p.seasons.includes(state.season)) return false;
      if (state.accords.length && !state.accords.every((a) => p.accords.some((x) => x.toLowerCase() === a.toLowerCase()))) return false;
      if (min !== null || max !== null) {
        const price = state.volume ? volPrice(p, state.volume) : p.min;
        if (!price || (min !== null && price < min) || (max !== null && price > max)) return false;
      }
      return true;
    });
    if (state.q.trim()) {
      const found = search(state.q, list);
      if (state.sort === 'popular') return found; // по релевантности
      list = found;
    }
    const price = (p) => (state.volume ? volPrice(p, state.volume) : p.min) || 1e9;
    const sorters = {
      popular: (a, b) => (b.inStock - a.inStock) || (b.pop - a.pop) || (b.hit - a.hit) || (!!b.image - !!a.image) || a.id - b.id,
      new: (a, b) => (b.inStock - a.inStock) || (b.isNew - a.isNew) || (b.newness - a.newness) || (b.id - a.id),
      price_asc: (a, b) => (b.inStock - a.inStock) || price(a) - price(b),
      price_desc: (a, b) => (b.inStock - a.inStock) || price(b) - price(a),
      rating: (a, b) => (b.rating - a.rating) || (b.reviews - a.reviews) || (b.pop - a.pop) || (b.inStock - a.inStock),
      name: (a, b) => a.name.localeCompare(b.name),
    };
    return list.sort(sorters[state.sort] || sorters.popular);
  };

  const renderFacetChips = () => {
    const base = scopeList();
    const sBox = $('#fSeason');
    if (sBox) {
      sBox.innerHTML = `<button type="button" class="chip ${state.season ? '' : 'is-active'}" data-v="">Любой</button>` + (CFG.seasons || []).map((s) => {
        const n = base.filter((p) => p.seasons.includes(s)).length;
        return `<button type="button" class="chip ${state.season === s ? 'is-active' : ''}" data-v="${esc(s)}" ${n ? '' : 'data-empty'}>${esc(s)}<sup>${n}</sup></button>`;
      }).join('');
    }
    const aBox = $('#fAccord');
    if (aBox) {
      const extra = [...new Set(base.flatMap((p) => p.accords))].filter((a) => !(CFG.accords || []).some((x) => x.toLowerCase() === a.toLowerCase()));
      aBox.innerHTML = [...(CFG.accords || []), ...extra].map((a) => {
        const n = base.filter((p) => p.accords.some((x) => x.toLowerCase() === a.toLowerCase())).length;
        const on = state.accords.some((x) => x.toLowerCase() === a.toLowerCase());
        return `<button type="button" class="chip ${on ? 'is-active' : ''}" data-v="${esc(a)}" ${n ? '' : 'data-empty'}>${esc(a)}<sup>${n}</sup></button>`;
      }).join('');
    }
  };

  const renderCatalog = () => {
    if (!$('#grid')) return;
    const list = filtered();
    const slice = list.slice(0, state.shown);
    $('#count').innerHTML = `Найдено <b>${list.length}</b> ${plural(list.length, 'аромат', 'аромата', 'ароматов')}`;
    $('#grid').innerHTML = slice.length ? slice.map(cardHtml).join('') : `
      <div class="empty"><h3>${scopeList().length ? 'Ничего не нашлось' : 'Подборка наполняется'}</h3><p>${scopeList().length ? 'Измените фильтры или оставьте заявку — поищем аромат для вас.' : 'Скоро здесь появятся ароматы.'}</p>
      ${scopeList().length ? '<button type="button" class="btn btn--line btn--sm" data-reset>Сбросить фильтры</button>' : `<a class="btn btn--line btn--sm" href="${BASE}/#catalog">Весь каталог</a>`}</div>`;
    const left = list.length - slice.length;
    $('#more').style.display = left > 0 ? '' : 'none';
    $('#moreInfo').textContent = list.length ? `Показано ${slice.length} из ${list.length}` : '';
    $('#moreBar').style.width = list.length ? (slice.length / list.length) * 100 + '%' : '0';
    renderTags();
  };

  const renderTags = () => {
    const tags = [];
    if (state.q) tags.push(['q', `«${state.q}»`]);
    if (state.gender) tags.push(['gender', GENDERS[state.gender]]);
    if (state.brand) tags.push(['brand', state.brand]);
    if (state.volume) tags.push(['volume', state.volume + ' мл']);
    if (state.min) tags.push(['min', 'от ' + money(state.min)]);
    if (state.max) tags.push(['max', 'до ' + money(state.max)]);
    if (state.stock) tags.push(['stock', 'В наличии']);
    if (state.fav) tags.push(['fav', 'Избранное']);
    if (state.season) tags.push(['season', state.season]);
    state.accords.forEach((a) => tags.push(['accord:' + a, a]));
    $('#activeTags').innerHTML = tags.map(([k, t]) => `<button type="button" class="tag" data-untag="${esc(k)}">${esc(t)} ${icon('close')}</button>`).join('');
  };

  const syncFilters = () => {
    if (!$('#grid')) return;
    $('#fSearch').value = state.q;
    $('#fMin').value = state.min;
    $('#fMax').value = state.max;
    $('#fStock').checked = state.stock;
    $('#fFav').checked = state.fav;
    $('#fSort').value = state.sort;
    $$('#fGender .chip').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.gender));
    $$('#fVolume .chip').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.volume));
    $$('#fBrand .f-opt').forEach((b) => b.classList.toggle('is-active', b.dataset.v === state.brand));
    renderFacetChips();
  };

  const setFilter = (patch, scroll) => {
    Object.assign(state, patch, { shown: PAGE });
    syncFilters();
    renderCatalog();
    if (scroll && $('#catalog')) $('#catalog').scrollIntoView({ behavior: 'smooth' });
  };

  const resetFilters = () => setFilter({ q: '', gender: '', volume: '', brand: '', min: '', max: '', stock: false, fav: false, season: '', accords: [] });

  /* ---------- Поиск в шапке ---------- */
  const renderSearch = () => {
    const q = $('#searchInput').value.trim();
    const box = $('#searchResults');
    if (q.length < 2) { box.innerHTML = ''; return; }
    const all = search(q);
    const hits = all.slice(0, 9);
    const brands = [...new Set(all.map((p) => p.brand))].filter((b) => tokens(b + ' ' + ((CFG.aliases || {})[b] || '')).some((t) => tokens(q).some((x) => t.startsWith(x) || (x.length >= 4 && lev(x, t.slice(0, x.length), 1) <= 1)))).slice(0, 3);
    box.innerHTML = (brands.map((b) => `<a href="${brandUrl(b)}" class="search-hit search-hit--brand"><span class="search-hit__ico">${icon('tag')}</span><div><b>Бренд</b><span>${esc(b)}</span></div></a>`).join('') + hits.map((p) => `
      <a href="${p.url}" class="search-hit">
        <img src="${esc(p.image ? BASE + p.image : PLACEHOLDER)}" alt="" loading="lazy">
        <div><b>${esc(p.brand)}</b><span>${esc(p.short)}</span><small>${p.inStock ? 'от ' + money(p.min) : 'нет в наличии'}</small></div>
      </a>`).join('') + (all.length > 9 ? `<a href="${BASE}/?q=${encodeURIComponent(q)}#catalog" class="search-hit" data-search-all><div><b>Все результаты · ${all.length}</b><span>Показать в каталоге</span></div></a>` : ''))
      || '<p class="search-empty">Ничего не нашлось. Попробуйте другое написание или <button type="button" class="u-link" data-ask="1">спросите нас</button>.</p>';
  };

  /* ---------- Страница аромата ---------- */
  const PRODUCT_ID = CFG.productId || 0;

  const renderBuybox = () => {
    const box = $('#buybox');
    const p = BY_ID.get(PRODUCT_ID);
    if (!box || !p) return;
    const v = volOf(p);
    const price = volPrice(p, v);
    const old = price < (p.volumes[v] || 0) ? p.volumes[v] : (p.discount ? p.base[v] : 0);
    const fav = state.favs.includes(p.id);
    const favBtn = `<button type="button" class="icon-btn icon-btn--lg ${fav ? 'is-active' : ''}" data-fav="${p.id}" aria-label="${fav ? 'Убрать из избранного' : 'В избранное'}">${icon('heart')}</button>`;
    box.innerHTML = `
      <div class="buybox__stock ${p.inStock ? 'is-in' : 'is-out'}">${p.inStock ? 'В наличии' : 'Нет в наличии'}</div>
      <div class="buybox__vols">${['5', '10', '15'].map((x) => {
        const has = p.volumes[x] && p.inStock;
        return `<button type="button" class="bvol ${has && x === v ? 'is-active' : ''}" data-vol="${x}" data-id="${p.id}" ${has ? '' : 'disabled'}><b>${x} мл</b><span>${p.volumes[x] ? money(volPrice(p, x)) : '—'}</span></button>`;
      }).join('')}</div>
      ${p.inStock
        ? `<div class="pv__price">${money(price)}${old ? `<s>${money(old)}</s>` : ''}<small>за ${v} мл</small></div>
           <div class="pv__actions"><button type="button" class="btn btn--primary" data-add="${p.id}" data-open-cart="1">В корзину ${icon('bag')}</button>${favBtn}</div>`
        : `<p class="muted">Аромата сейчас нет — забронируйте, и мы сообщим о поступлении.</p>
           <div class="pv__actions"><button type="button" class="btn btn--primary" data-book="${p.id}">Забронировать</button>${favBtn}</div>`}`;
  };

  const RECENT_KEY = 'ps_recent';
  const trackRecent = () => {
    if (!PRODUCT_ID) return;
    const list = load(RECENT_KEY, []).filter((id) => id !== PRODUCT_ID && BY_ID.has(id));
    const shown = list.slice(0, 12).map((id) => BY_ID.get(id));
    if (shown.length && $('#recentRail')) {
      $('#recentRail').innerHTML = shown.map(cardHtml).join('');
      $('#recent').hidden = false;
    }
    list.unshift(PRODUCT_ID);
    save(RECENT_KEY, list.slice(0, 20));
  };

  const gallery = () => {
    const track = $('#galleryTrack');
    if (!track) return;
    const slides = $$('.gallery__slide', track);
    const marks = $$('#gallery [data-slide]');
    const current = () => Math.round(track.scrollLeft / track.clientWidth);
    const go = (i) => track.scrollTo({ left: Math.max(0, Math.min(slides.length - 1, i)) * track.clientWidth, behavior: 'smooth' });
    track.addEventListener('scroll', () => {
      const i = current();
      marks.forEach((m) => m.classList.toggle('is-active', Number(m.dataset.slide) === i));
    }, { passive: true });
    $('#gallery').addEventListener('click', (e) => {
      const b = e.target.closest('[data-gallery], [data-slide]');
      if (!b) return;
      if (b.dataset.gallery) go(current() + Number(b.dataset.gallery));
      else go(Number(b.dataset.slide));
    });
  };

  /* ---------- Избранное ---------- */
  const toggleFav = (id) => {
    const i = state.favs.indexOf(id);
    if (i >= 0) state.favs.splice(i, 1); else { state.favs.push(id); toast('Добавлено в избранное', { label: 'Открыть', open: 'favs' }); }
    save('ps_favs', state.favs);
    syncFavButtons();
    renderFavs();
    counters();
    if (state.fav) renderCatalog();
  };

  const renderFavs = () => {
    const list = state.favs.map((id) => BY_ID.get(id)).filter(Boolean);
    $('#favQty').textContent = list.length || '';
    $('#favBody').innerHTML = list.length ? list.map((p) => `
      <div class="line-item">
        <a href="${p.url}"><img class="line-item__img" src="${esc(p.image ? BASE + p.image : PLACEHOLDER)}" alt="" loading="lazy"></a>
        <div>
          <div class="line-item__brand">${esc(p.brand)}</div>
          <div class="line-item__name"><a href="${p.url}">${esc(p.short)}</a></div>
          <div class="line-item__vol">${p.inStock ? 'от ' + money(p.min) : 'Нет в наличии'}</div>
        </div>
        <div class="line-item__right">
          <button type="button" class="remove" data-fav="${p.id}">Убрать</button>
          ${p.inStock ? `<button type="button" class="icon-btn icon-btn--primary" data-add="${p.id}" aria-label="В корзину">${icon('bag')}</button>` : ''}
        </div>
      </div>`).join('') : emptyBlock('heart', 'Здесь пока пусто', 'Нажмите на сердечко в карточке аромата, чтобы сохранить его.');
  };

  /* ---------- Корзина и оформление ---------- */
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

  const promoDiscount = (promo, base) => {
    if (!promo) return 0;
    if (promo.min_sum && base < promo.min_sum) return 0;
    const d = promo.type === 'amount' ? promo.value : Math.round(base * promo.value / 100);
    return Math.max(0, Math.min(d, base));
  };

  const totals = () => {
    let sum = 0, qty = 0;
    state.cart.forEach((i) => { const p = BY_ID.get(i.id); sum += volPrice(p, i.volume) * i.qty; qty += i.qty; });
    const discount = qty >= BUNDLE_MIN ? Math.round(sum * BUNDLE_PCT / 100) : 0;
    const user = currentUser();
    const promo = state.promo;
    const promoSum = promoDiscount(promo, sum - discount);
    const promoNote = promo && !promoSum && promo.min_sum ? `Промокод действует от ${money(promo.min_sum)}` : '';
    const after = sum - discount - promoSum;
    const maxPoints = user ? Math.min(user.points, Math.floor(after * LOYALTY_MAX / 100)) : 0;
    const points = state.usePoints ? maxPoints : 0;
    const total = after - points;
    const gift = after >= GIFT_FROM;
    if (!gift) state.giftId = 0;
    const giftProduct = gift && state.giftId ? BY_ID.get(state.giftId) : null;
    return { sum, qty, discount, promo, promoSum, promoNote, points, maxPoints, after, total, gift, giftProduct, user };
  };

  const addToCart = (id, btn) => {
    const p = BY_ID.get(id);
    if (!p || !p.inStock) return;
    const volume = volOf(p);
    const line = state.cart.find((i) => i.id === id && i.volume === volume);
    if (line) line.qty += 1; else state.cart.push({ id, volume, qty: 1 });
    save('ps_cart', state.cart);
    state.step = 'cart';
    counters();
    renderCart();
    goal('add_to_cart', { id, volume });
    if (btn && btn.dataset.openCart) { open('cart'); return; }
    toast(`${p.short}, ${volume} мл — в корзине`, { label: 'Оформить', open: 'cart' });
    if (btn && btn.classList.contains('icon-btn')) {
      btn.classList.add('is-done');
      btn.innerHTML = icon('check');
      setTimeout(() => { btn.classList.remove('is-done'); btn.innerHTML = icon('bag'); }, 1400);
    }
  };

  const STEPS = [['cart', 'Корзина'], ['contacts', 'Данные'], ['delivery', 'Доставка'], ['payment', 'Оплата']];
  const stepper = () => `<ol class="stepper">${STEPS.map(([k, t], i) => {
    const idx = STEPS.findIndex((s) => s[0] === state.step);
    return `<li class="${i < idx ? 'is-done' : ''} ${i === idx ? 'is-active' : ''}">${i < idx ? `<button type="button" data-step="${k}">${t}</button>` : `<span>${t}</span>`}</li>`;
  }).join('')}</ol>`;

  const hintsHtml = (t) => {
    const needItems = Math.max(0, BUNDLE_MIN - t.qty);
    const needSum = Math.max(0, GIFT_FROM - t.after);
    return `
      <div class="goal">
        <div class="hint-row ${t.discount ? 'is-ok' : ''}">
          <div class="hint-row__text">${t.discount
            ? `${icon('check')} Скидка ${BUNDLE_PCT}% применена`
            : `${icon('tag')} Добавьте ещё <b>${needItems} ${plural(needItems, 'аромат', 'аромата', 'ароматов')}</b> — получите скидку ${BUNDLE_PCT}%`}</div>
          <div class="bar"><i style="width:${Math.min(100, t.qty / BUNDLE_MIN * 100)}%"></i></div>
        </div>
        <div class="hint-row ${t.gift ? 'is-ok' : ''}">
          <div class="hint-row__text">${t.gift
            ? `${icon('gift')} Подарочный пробник 2 мл — <button type="button" class="u-link" data-gift-pick="1">${t.giftProduct ? 'изменить аромат' : 'выберите аромат'}</button>`
            : `${icon('gift')} До подарочного пробника осталось <b>${money(needSum)}</b>`}</div>
          <div class="bar"><i style="width:${Math.min(100, t.after / GIFT_FROM * 100)}%"></i></div>
        </div>
        <div class="hint-row is-ok"><div class="hint-row__text">${icon('fast')} Бесплатная доставка по всей России</div></div>
      </div>`;
  };

  const totalsHtml = (t) => `
    <div class="totals">
      <div><span>Товары, ${t.qty} шт.</span><span>${money(t.sum)}</span></div>
      ${t.discount ? `<div class="discount"><span>Скидка ${BUNDLE_PCT}% за ${BUNDLE_MIN}+ позиций</span><span>−${money(t.discount)}</span></div>` : ''}
      ${t.promoSum ? `<div class="discount"><span>Промокод ${esc(t.promo.code)}</span><span>−${money(t.promoSum)}</span></div>` : ''}
      ${t.points ? `<div class="discount"><span>Баллы</span><span>−${money(t.points)}</span></div>` : ''}
      ${t.gift ? `<div class="discount"><span>Пробник 2 мл в подарок</span><span>${t.giftProduct ? esc(t.giftProduct.short) : 'выберите аромат'}</span></div>` : ''}
      <div class="discount"><span>Доставка</span><span>Бесплатно</span></div>
      <div class="grand"><span>Итого</span><span>${money(t.total)}</span></div>
      <small>Бесплатная доставка по всей России. Скидка от ${BUNDLE_MIN} позиций применяется автоматически.</small>
    </div>`;

  const f = (k, d = '') => esc(state.form[k] ?? d);
  const radio = (name, value, label, sub) => `<label class="opt-card"><input type="radio" name="${name}" value="${esc(value)}" ${(state.form[name] || '') === value ? 'checked' : ''}><span><b>${label}</b>${sub ? `<small>${sub}</small>` : ''}</span></label>`;

  const renderCart = () => {
    const t = totals();
    $('#cartQty').textContent = t.qty || '';
    const body = $('#cartBody');
    const foot = $('#cartFoot');

    if (state.lastOrder) {
      const o = state.lastOrder;
      body.innerHTML = `<div class="drawer-empty done">${icon('check')}<h3>${o.type === 'booking' ? 'Бронь' : 'Заказ'} ${esc(o.id)} принят</h3>
        <p>${esc(o.message || '')}</p>
        <ol class="next-steps"><li>Менеджер проверит наличие и подтвердит заказ</li><li>Пришлём ссылку на оплату (${esc(o.payment || 'СБП или карта')})</li><li>Отправим бесплатно, трек-номер — в «Мои заказы»</li></ol>
        <button type="button" class="btn btn--line btn--sm" data-open="orders">Мои заказы</button></div>`;
      foot.innerHTML = '';
      return;
    }
    if (!state.cart.length) {
      state.step = 'cart';
      body.innerHTML = emptyBlock('bag', 'Корзина пуста', 'Выберите аромат и объём в каталоге.', `<a href="${BASE}/#catalog" class="btn btn--primary btn--sm" data-close="cart">В каталог</a>`);
      foot.innerHTML = '';
      return;
    }

    if (state.pickingGift && t.gift) {
      const list = (state.giftQuery ? search(state.giftQuery) : PRODUCTS).filter((p) => p.inStock).slice(0, 40);
      body.innerHTML = `<button type="button" class="back" data-gift-pick="0">${icon('left')} К корзине</button>
        <p>Выберите аромат для подарочного пробника 2 мл.</p>
        <div class="gift-pick"><input class="input" id="giftSearch" placeholder="Бренд или название" value="${esc(state.giftQuery)}"></div>
        ${list.map((p) => `<button type="button" class="gift-hit" data-gift="${p.id}"><img src="${esc(p.image ? BASE + p.image : PLACEHOLDER)}" alt=""><span><b>${esc(p.brand)}</b><br>${esc(p.short)}</span></button>`).join('')}`;
      foot.innerHTML = '';
      return;
    }

    if (state.step === 'contacts') {
      body.innerHTML = stepper() + `
        <form class="form checkout" id="stepForm" data-next="delivery" novalidate>
          <div class="seg" role="radiogroup" aria-label="Тип заказа">
            <label><input type="radio" name="order_mode" value="buy" ${state.form.order_mode !== 'book' ? 'checked' : ''}><span>Купить</span></label>
            <label><input type="radio" name="order_mode" value="book" ${state.form.order_mode === 'book' ? 'checked' : ''}><span>Забронировать</span></label>
          </div>
          <div class="field"><label for="oName">Имя</label><input class="input" id="oName" name="name" autocomplete="name" value="${f('name')}" required></div>
          <div class="field"><label for="oPhone">Телефон</label><input class="input" id="oPhone" name="phone" type="tel" autocomplete="tel" placeholder="+7" value="${f('phone', state.phone)}" required></div>
          <div class="field"><label for="oEmail">Почта</label><input class="input" id="oEmail" name="email" type="email" autocomplete="email" value="${f('email')}" required><small class="hint">Пришлём чек и номер отслеживания</small></div>
        </form>`;
      foot.innerHTML = `<div class="foot-sum"><span>Итого</span><b>${money(t.total)}</b></div><div class="foot-nav"><button type="button" class="btn btn--ghost" data-step="cart">${icon('left')} Назад</button><button type="submit" form="stepForm" class="btn btn--primary">Далее: доставка ${icon('arrow')}</button></div>`;
      return;
    }

    if (state.step === 'delivery') {
      if (!state.form.delivery) state.form.delivery = 'СДЭК';
      body.innerHTML = stepper() + `
        <form class="form checkout" id="stepForm" data-next="payment" novalidate>
          <div class="free-ship">${icon('fast')} Доставка бесплатная — по всей России</div>
          <div class="opt-list">
            ${radio('delivery', 'СДЭК', 'СДЭК', 'До пункта выдачи или курьером')}
            ${radio('delivery', 'Почта России', 'Почта России', 'В любой населённый пункт')}
            ${radio('delivery', 'Яндекс Доставка', 'Яндекс Доставка', 'Пункты выдачи и курьер')}
          </div>
          <div class="field"><label for="oCity">Город</label><input class="input" id="oCity" name="city" autocomplete="address-level2" value="${f('city')}" required></div>
          <div class="field"><label for="oAddress">Адрес или пункт выдачи</label><input class="input" id="oAddress" name="address" autocomplete="street-address" value="${f('address')}" required></div>
          <div class="field"><label for="oComment">Комментарий</label><input class="input" id="oComment" name="comment" value="${f('comment')}" placeholder="Необязательно"></div>
        </form>`;
      foot.innerHTML = `<div class="foot-sum"><span>Итого</span><b>${money(t.total)}</b></div><div class="foot-nav"><button type="button" class="btn btn--ghost" data-step="contacts">${icon('left')} Назад</button><button type="submit" form="stepForm" class="btn btn--primary">Далее: оплата ${icon('arrow')}</button></div>`;
      return;
    }

    if (state.step === 'payment') {
      if (!state.form.payment) state.form.payment = 'СБП';
      body.innerHTML = stepper() + `
        <form class="form checkout" id="orderForm" novalidate>
          <div class="opt-list">
            ${radio('payment', 'СБП', 'СБП', 'Через приложение банка по QR-коду или ссылке')}
            ${radio('payment', 'Банковская карта', 'Банковская карта', 'Онлайн, чек придёт на почту')}
          </div>
          <p class="hint">После оформления менеджер подтвердит заказ и пришлёт ссылку на оплату.</p>
          <div class="field"><label for="oPromo">Промокод</label>
            <div class="promo-field"><input class="input" id="oPromo" name="promo" value="${esc(t.promo ? t.promo.code : (state.form.promo || ''))}" placeholder="Введите промокод" autocomplete="off"><button class="btn btn--line btn--sm" type="button" data-apply-promo="1">${t.promo ? 'Изменить' : 'Применить'}</button></div>
            ${t.promo ? `<small class="ok-text">${icon('check')} ${esc(t.promo.title || 'Промокод применён')}${t.promoNote ? ' · ' + esc(t.promoNote) : ''} <button type="button" class="u-link" data-drop-promo="1">убрать</button></small>` : ''}
            <small class="muted" id="promoMsg"></small>
          </div>
          ${t.user && t.maxPoints ? `<label class="points-line"><input type="checkbox" name="use_points" ${state.usePoints ? 'checked' : ''}> Списать ${t.maxPoints} баллов (до ${LOYALTY_MAX}% заказа)</label>` : `<p class="muted">Бонусы копятся в личном кабинете: ${LOYALTY_PCT}% от чека.</p>`}
          <div class="review-box">
            <div><span>Получатель</span><b>${f('name')}, ${f('phone')}</b></div>
            <div><span>Доставка</span><b>${f('delivery')} · ${f('city')}, ${f('address')}</b></div>
          </div>
          <label class="consent"><input type="checkbox" name="consent" required><span>Согласен с <a href="${BASE}/oferta/" target="_blank">офертой</a> и на <a href="${BASE}/soglasie/" target="_blank">обработку персональных данных</a></span></label>
        </form>`;
      foot.innerHTML = totalsHtml(t) + `<div class="foot-nav"><button type="button" class="btn btn--ghost" data-step="delivery">${icon('left')} Назад</button><button type="submit" form="orderForm" class="btn btn--primary">${state.form.order_mode === 'book' ? 'Забронировать' : 'Подтвердить заказ'}</button></div>`;
      return;
    }

    body.innerHTML = stepper() + hintsHtml(t) + state.cart.map((i, idx) => {
      const p = BY_ID.get(i.id);
      return `
        <div class="line-item">
          <a href="${p.url}"><img class="line-item__img" src="${esc(p.image ? BASE + p.image : PLACEHOLDER)}" alt="${esc(p.name)}" loading="lazy"></a>
          <div>
            <div class="line-item__brand">${esc(p.brand)}</div>
            <div class="line-item__name"><a href="${p.url}">${esc(p.short)}</a></div>
            <div class="line-item__vol">${i.volume} мл · ${money(volPrice(p, i.volume))} за шт.</div>
            <div class="qty"><button type="button" data-qty="-1" data-idx="${idx}" aria-label="Меньше">−</button><span>${i.qty}</span><button type="button" data-qty="1" data-idx="${idx}" aria-label="Больше">+</button></div>
          </div>
          <div class="line-item__right">
            <div class="line-item__sum">${money(volPrice(p, i.volume) * i.qty)}</div>
            <button type="button" class="remove" data-remove="${idx}">Удалить</button>
          </div>
        </div>`;
    }).join('') + (t.gift ? `<button type="button" class="gift-line" data-gift-pick="1">${icon('gift')}<span>${t.giftProduct ? `${esc(t.giftProduct.brand)} ${esc(t.giftProduct.short)}<small>Подарочный пробник 2 мл · нажмите, чтобы изменить</small>` : 'Выбрать аромат для подарочного пробника 2 мл'}</span></button>` : '');
    foot.innerHTML = totalsHtml(t) + `<button type="button" class="btn btn--primary btn--block" data-step="contacts">Оформить заказ ${icon('arrow')}</button>`;
  };

  const goStep = (step) => {
    state.step = step;
    renderCart();
    const b = $('#cartBody');
    if (b) b.scrollTop = 0;
  };

  const submitStep = (form) => {
    const fd = new FormData(form);
    fd.forEach((v, k) => { state.form[k] = String(v).trim(); });
    if (form.dataset.next === 'delivery') {
      const err = validate(fd, false);
      if (err) return toast(err);
      state.phone = state.form.phone; save('ps_phone', state.phone);
    }
    if (form.dataset.next === 'payment') {
      if (!state.form.city) return toast('Укажите город');
      if (!state.form.address) return toast('Укажите адрес или пункт выдачи');
    }
    save('ps_checkout', state.form);
    goStep(form.dataset.next);
  };

  const applyPromo = async () => {
    const code = ($('#oPromo')?.value || '').trim().toUpperCase();
    const msg = $('#promoMsg');
    if (!code) { state.promo = null; save('ps_promo2', null); renderCart(); return; }
    const t = totals();
    const res = await post('check_promo', { code, subtotal: t.sum - t.discount, phone: state.form.phone || state.phone, email: state.form.email || '' });
    if (!res.ok) { if (msg) msg.textContent = res.error || 'Промокод не найден'; return; }
    state.promo = res.promo;
    save('ps_promo2', state.promo);
    renderCart();
    toast(`Промокод ${res.promo.code} применён`);
  };

  const submitOrder = async (form) => {
    const fd = new FormData(form);
    fd.forEach((v, k) => { state.form[k] = String(v).trim(); });
    if (fd.get('consent') !== 'on') return toast('Нужно согласие с офертой и на обработку персональных данных');
    const pointsBox = form.querySelector('[name=use_points]');
    state.usePoints = !!(pointsBox && pointsBox.checked);
    const t = totals();
    if (t.gift && !t.giftProduct) { toast('Выберите аромат для подарочного пробника'); state.pickingGift = true; renderCart(); return; }
    const btn = $('#cartFoot button[type=submit]');
    btn.disabled = true;
    const payload = { ...state.form, consent: true, items: state.cart, promo_code: t.promoSum ? t.promo.code : '', points_spend: t.points, gift_id: t.giftProduct ? t.giftProduct.id : 0 };
    payload.address = [state.form.city, state.form.address].filter(Boolean).join(', ');
    const res = await post('create_order', payload);
    btn.disabled = false;
    if (!res.ok) return toast(res.error || 'Не удалось отправить заказ');
    state.orders.unshift(res.order);
    save('ps_orders', state.orders);
    const user = currentUser();
    if (user) {
      user.orders.push(res.order.id);
      user.points = Math.max(0, user.points - t.points) + Math.round(res.order.total * LOYALTY_PCT / 100);
      saveUser(user);
    }
    state.cart = []; state.giftId = 0; state.promo = null; state.usePoints = false; state.step = 'cart';
    state.form = { name: state.form.name, phone: state.form.phone, email: state.form.email, city: state.form.city, address: state.form.address };
    state.lastOrder = { ...res.order, message: res.message };
    save('ps_cart', state.cart); save('ps_gift', 0); save('ps_promo2', null); save('ps_checkout', state.form);
    note(`${res.order.type === 'booking' ? 'Бронь' : 'Заказ'} ${res.order.id} принят`, res.message);
    goal(res.order.type === 'booking' ? 'booking_submit' : 'order_submit', { order_id: res.order.id, total: res.order.total });
    counters();
    renderCart();
  };

  /* ---------- Заказы ---------- */
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
    if ($('#orders').classList.contains('is-open')) renderOrders();
  };

  /* ---------- Уведомления ---------- */
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

  /* ---------- Общие элементы ---------- */
  const counters = () => {
    const set = (k, n) => { const el = $(`[data-count="${k}"]`); if (el) el.textContent = n > 0 ? n : ''; };
    set('cart', totals().qty);
    set('favs', state.favs.length);
    set('orders', state.orders.filter((o) => o.status !== 'completed').length);
    set('notes', state.notes.filter((n) => n.unread).length);
  };

  const emptyBlock = (ic, title, text, extra = '') => `<div class="drawer-empty">${icon(ic)}<h3>${title}</h3><p>${text}</p>${extra}</div>`;

  const toast = (msg, action) => {
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = icon('check') + `<span>${esc(msg)}</span>` + (action ? `<button type="button" class="toast__btn" data-open="${esc(action.open)}">${esc(action.label)}</button>` : '');
    $('#toasts').appendChild(el);
    setTimeout(() => el.remove(), 4200);
  };

  const validate = (fd, needConsent = true) => {
    if (!String(fd.get('name') || '').trim()) return 'Укажите имя';
    if (String(fd.get('phone') || '').replace(/\D/g, '').length < 10) return 'Проверьте телефон';
    if (!/^\S+@\S+\.\S+$/.test(String(fd.get('email') || ''))) return 'Проверьте почту';
    if (needConsent && fd.get('consent') !== 'on') return 'Нужно согласие на обработку персональных данных';
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

  const PANELS = ['cart', 'favs', 'orders', 'notes', 'account', 'filters'];
  const open = (id) => {
    if (!$('#' + id)) return;
    PANELS.forEach((x) => x !== id && $('#' + x)?.classList.remove('is-open'));
    if (id === 'cart') { if (state.lastOrder && state.cart.length) state.lastOrder = null; renderCart(); }
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
    if (id === 'cart' && state.lastOrder) state.lastOrder = null;
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
    const section = $('#request');
    if (!section) return;
    section.hidden = false;
    $('#lType').value = p.inStock ? 'Забронировать аромат' : 'Сообщить о поступлении';
    $('#lPerfume').value = `${p.name}${p.inStock ? ', ' + volOf(p) + ' мл' : ''}`;
    section.scrollIntoView({ behavior: 'smooth' });
    setTimeout(() => $('#lName').focus({ preventScroll: true }), 700);
  };

  /* ---------- Кабинет, вопросы ---------- */
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
        box.querySelectorAll('.reg').forEach((fld) => { fld.hidden = e.target.value !== 'register'; });
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
      <p>${state.favs.length ? state.favs.length + ' ' + plural(state.favs.length, 'аромат', 'аромата', 'ароматов') : 'Пока пусто'}</p>
      <p style="margin-top:8px">В корзине: ${totals().qty} шт.</p>
      <button class="btn btn--line" type="button" data-logout="1" style="margin-top:18px">Выйти</button>`;
  };

  const answerQuestion = (q) => {
    const text = q.toLowerCase();
    const hard = /доставк|оплат|возврат|чек|жалоб|оригинал|документ|менеджер|скидк|промокод|балл/.test(text);
    const found = search(text.replace(/[?!.,]/g, ' ').replace(/\b(есть|ли|у|вас|в|наличии|сколько|стоит|а|какой|какая|хочу|нужен|нужна|подскажите)\b/gi, ' ')).slice(0, 3);
    if (!hard && found.length && found.length < PRODUCTS.length) {
      return found.map((p) => `${esc(p.brand)} ${esc(p.short)}: ${p.inStock ? 'в наличии, от ' + money(p.min) : 'сейчас нет'}. <a href="${p.url}">Открыть</a>`).join('<br><br>');
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

  /* ---------- События ---------- */
  document.addEventListener('click', (e) => {
    const t = e.target.closest('button, a');
    if (!t) return;
    const d = t.dataset;

    if (d.vol) {
      const id = Number(d.id);
      state.picked[id] = d.vol;
      if (t.classList.contains('bvol')) renderBuybox(); else refreshCard(id);
      return;
    }
    if (d.add) { addToCart(Number(d.add), t); return; }
    if (d.book) { book(Number(d.book)); return; }
    if (d.fav) { toggleFav(Number(d.fav)); return; }
    if (d.open) { e.preventDefault(); if (d.open === 'search') toggleSearch(); else open(d.open); return; }
    if (d.close) { if (d.close === 'search') toggleSearch(false); else close(d.close); return; }
    if (d.untag) {
      const k = d.untag;
      if (k.startsWith('accord:')) setFilter({ accords: state.accords.filter((a) => a !== k.slice(7)) });
      else setFilter({ [k]: typeof state[k] === 'boolean' ? false : '' });
      return;
    }
    if (d.reset !== undefined) { resetFilters(); return; }
    if (d.step) { goStep(d.step); return; }
    if (d.giftPick !== undefined) { state.pickingGift = d.giftPick === '1'; renderCart(); return; }
    if (d.gift) { state.giftId = Number(d.gift); state.pickingGift = false; save('ps_gift', state.giftId); renderCart(); return; }
    if (d.applyPromo !== undefined) { applyPromo(); return; }
    if (d.dropPromo !== undefined) { state.promo = null; save('ps_promo2', null); renderCart(); return; }
    if (d.copy) { navigator.clipboard && navigator.clipboard.writeText(d.copy); state.form.promo = d.copy; save('ps_checkout', state.form); toast('Промокод ' + d.copy + ' скопирован — введите его при оплате'); return; }
    if (d.ask !== undefined) { $('#askBox').hidden = d.ask !== '1'; if (d.ask === '1' && !$('#askLog').childElementCount) askLog('Спросите про аромат — подскажу по каталогу. Сложный вопрос передам менеджеру.', 'bot'); return; }
    if (d.logout) { state.user = null; save('ps_user', null); renderAccount(); return; }
    if (d.qty) {
      const line = state.cart[Number(d.idx)];
      line.qty += Number(d.qty);
      if (line.qty <= 0) state.cart.splice(Number(d.idx), 1);
      save('ps_cart', state.cart); counters(); renderCart(); return;
    }
    if (d.remove) { state.cart.splice(Number(d.remove), 1); save('ps_cart', state.cart); counters(); renderCart(); return; }
    if (d.rail) { const r = $('#' + (d.target || 'hits')); if (r) r.scrollBy({ left: Number(d.rail) * r.clientWidth * 0.75, behavior: 'smooth' }); return; }
    if (d.simtab) {
      $$('[data-simtab]').forEach((b) => b.classList.toggle('is-active', b === t));
      $$('[data-simpane]').forEach((p) => { p.hidden = p.dataset.simpane !== d.simtab; });
      return;
    }
    if (d.searchAll !== undefined && $('#grid') && !SCOPE) { e.preventDefault(); setFilter({ q: $('#searchInput').value }, true); toggleSearch(false); return; }
    if (d.goal) goal(d.goal);
    if (t.closest('#nav') && !t.closest('.nav__item--mega > .nav__link')) $('#nav').classList.remove('is-open');
    if (t.matches('.nav__item--mega > .nav__link') && window.matchMedia('(max-width: 1080px)').matches) { e.preventDefault(); t.parentElement.classList.toggle('is-open'); }
  });

  $('#overlay').addEventListener('click', closeAll);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeAll(); toggleSearch(false); } });
  $('#burger').addEventListener('click', () => $('#nav').classList.toggle('is-open'));

  $('#searchInput').addEventListener('input', renderSearch);
  $('#searchInput').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const q = e.target.value.trim();
    if (!q) return;
    if ($('#grid') && !SCOPE) { setFilter({ q }, true); toggleSearch(false); } else location.href = `${BASE}/?q=${encodeURIComponent(q)}#catalog`;
  });

  if ($('#grid')) {
    let typing;
    $('#fSearch').addEventListener('input', (e) => { clearTimeout(typing); typing = setTimeout(() => setFilter({ q: e.target.value }), 180); });
    $('#fMin').addEventListener('change', (e) => setFilter({ min: e.target.value }));
    $('#fMax').addEventListener('change', (e) => setFilter({ max: e.target.value }));
    $('#fStock').addEventListener('change', (e) => setFilter({ stock: e.target.checked }));
    $('#fFav').addEventListener('change', (e) => setFilter({ fav: e.target.checked }));
    $('#fSort').addEventListener('change', (e) => setFilter({ sort: e.target.value }));
    $('#fGender').addEventListener('click', (e) => { const b = e.target.closest('.chip'); if (b) setFilter({ gender: b.dataset.v }); });
    $('#fVolume').addEventListener('click', (e) => { const b = e.target.closest('.chip'); if (b) setFilter({ volume: b.dataset.v }); });
    $('#fSeason').addEventListener('click', (e) => { const b = e.target.closest('.chip'); if (b) setFilter({ season: b.dataset.v }); });
    $('#fAccord').addEventListener('click', (e) => {
      const b = e.target.closest('.chip');
      if (!b) return;
      const v = b.dataset.v;
      setFilter({ accords: state.accords.includes(v) ? state.accords.filter((a) => a !== v) : [...state.accords, v] });
    });
    $('#fBrand')?.addEventListener('click', (e) => { const b = e.target.closest('.f-opt'); if (b) setFilter({ brand: b.dataset.v }); });
    $('#fReset').addEventListener('click', resetFilters);
    $('#more').addEventListener('click', () => { state.shown += PAGE; renderCatalog(); });
  }

  document.addEventListener('input', (e) => {
    if (e.target.id !== 'giftSearch') return;
    state.giftQuery = e.target.value;
    const pos = e.target.selectionStart;
    renderCart();
    const el = $('#giftSearch');
    if (el) { el.focus(); el.setSelectionRange(pos, pos); }
  });
  document.addEventListener('change', (e) => {
    const fm = e.target.form;
    if (fm && (fm.id === 'stepForm' || fm.id === 'orderForm') && e.target.type === 'radio') { state.form[e.target.name] = e.target.value; save('ps_checkout', state.form); }
    if (fm && fm.id === 'orderForm' && e.target.name === 'use_points') { state.usePoints = e.target.checked; renderCart(); }
  });

  $('#orderTabs').addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) { state.ordersTab = b.dataset.v; renderOrders(); } });

  $('#abc')?.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    $$('#abc button').forEach((x) => x.classList.toggle('is-active', x === b));
    $$('.brand-group').forEach((g) => { g.style.display = b.dataset.letter === 'ALL' || g.dataset.group === b.dataset.letter ? '' : 'none'; });
  });

  const findByName = (v) => {
    const s = String(v || '').trim().toLowerCase();
    if (!s) return null;
    return PRODUCTS.find((p) => p.name.toLowerCase() === s) || search(s)[0] || null;
  };
  const fillNames = (id) => { const dl = $(id); if (dl && !dl.childElementCount) dl.innerHTML = PRODUCTS.map((p) => `<option value="${esc(p.name)}">`).join(''); };
  ['#rPerfume', '#suggestInput'].forEach((sel) => $(sel)?.addEventListener('focus', () => fillNames(sel === '#rPerfume' ? '#rPerfumeList' : '#suggestList'), { once: true }));

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.id === 'stepForm') { e.preventDefault(); submitStep(form); }
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
      let pid = Number(fd.get('product_id') || 0);
      let perfume = String(fd.get('perfume') || '');
      if (!pid && perfume) { const p = findByName(perfume); if (p) { pid = p.id; perfume = p.name; } }
      const res = await post('create_review', { name: fd.get('name'), product_id: pid, perfume, text: fd.get('text'), rating: fd.get('rating'), consent: true });
      if (!res.ok) return toast(res.error || 'Не удалось отправить отзыв');
      form.reset();
      toast(res.message);
    }
    if (form.id === 'suggestForm') {
      e.preventDefault();
      const p = findByName(new FormData(form).get('q'));
      if (!p || p.id === PRODUCT_ID) return toast('Выберите аромат из списка');
      const key = 'ps_votes';
      const voted = load(key, []);
      const pair = `${PRODUCT_ID}:${p.id}`;
      if (voted.includes(pair)) return toast('Вы уже предлагали этот аромат');
      const res = await post('vote_similar', { product_id: PRODUCT_ID, similar_id: p.id });
      if (!res.ok) return toast(res.error || 'Не удалось отправить');
      voted.push(pair); save(key, voted);
      form.reset();
      toast(`Спасибо! ${p.short} учтён в подборке похожих`);
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

  try { if (!localStorage.getItem('ps_cookie')) $('#cookie').classList.add('is-open'); } catch (e) {}
  $('#cookieOk').addEventListener('click', () => { try { localStorage.setItem('ps_cookie', '1'); } catch (e) {} $('#cookie').classList.remove('is-open'); });

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
  renderCatalog();
  syncFavButtons();
  renderBuybox();
  trackRecent();
  gallery();
  counters();
  refreshOrders();
  if ((params.get('gender') || params.get('brand') || params.get('q')) && $('#catalog')) {
    requestAnimationFrame(() => $('#catalog').scrollIntoView({ behavior: 'auto' }));
  }
})();
