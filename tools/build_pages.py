#!/usr/bin/env python3
"""Статическая демо-сборка для GitHub Pages.

Обходит все страницы локального PHP-сервера (по sitemap.xml) и сохраняет их как
HTML с путями проекта (https://<user>.github.io/<repo>/). Заказы, промокоды и отзывы
в демо работают через tools/demo-api.js и хранятся только в браузере посетителя.

Запуск: php -S 127.0.0.1:8085 -t . router.php &
        python3 tools/build_pages.py /tmp/ps-pages /perfume-space
"""
import json
import re
import shutil
import sys
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SERVER = 'http://127.0.0.1:8085'
OUT = Path(sys.argv[1] if len(sys.argv) > 1 else '/tmp/ps-pages')
BASE = (sys.argv[2] if len(sys.argv) > 2 else '/perfume-space').rstrip('/')


def fetch(path: str) -> str:
    try:
        with urllib.request.urlopen(SERVER + path) as r:
            return r.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        return e.read().decode('utf-8')


config = json.loads((ROOT / 'data/site_config.json').read_text(encoding='utf-8'))
demo_promos = []
for p in config.get('promo_codes', []):
    if not p.get('active'):
        continue
    typ = 'amount' if p.get('type') == 'amount' else 'percent'
    demo_promos.append({
        'code': p['code'], 'type': typ, 'value': int(p.get('value', p.get('percent', 0)) or 0),
        'min_sum': int(p.get('min_sum') or 0), 'expires': p.get('expires', ''), 'once': bool(p.get('once')),
        'first_order': bool(p.get('first_order')), 'title': p.get('title', ''), 'active': True,
    })
DEMO_SCRIPTS = (f'<script>window.PS_DEMO_PROMOS = {json.dumps(demo_promos, ensure_ascii=False)};</script>\n'
                f'<script src="{BASE}/assets/js/demo-api.js" defer></script>\n')


def rewrite(html: str) -> str:
    html = re.sub(r'(href|src|action)="/(?!/)', rf'\1="{BASE}/', html)
    html = html.replace('"base":""', f'"base":"{BASE}"')
    html = re.sub(r'<meta name="robots" content="[^"]*">', '<meta name="robots" content="noindex, nofollow">', html)
    html = html.replace(f'<script src="{BASE}/assets/js/app.js', DEMO_SCRIPTS + f'<script src="{BASE}/assets/js/app.js')
    return html


def out_path(url: str) -> Path:
    url = url.split('?')[0].strip('/')
    return OUT / url / 'index.html' if url else OUT / 'index.html'


if OUT.exists():
    for item in OUT.iterdir():
        if item.name != '.git':
            shutil.rmtree(item) if item.is_dir() else item.unlink()
OUT.mkdir(parents=True, exist_ok=True)

sitemap = fetch('/sitemap.xml')
paths = sorted({re.sub(r'^https?://[^/]+', '', u) for u in re.findall(r'<loc>([^<]+)</loc>', sitemap)})


def build(path: str) -> None:
    dest = out_path(path)
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(rewrite(fetch(path)), encoding='utf-8')


with ThreadPoolExecutor(max_workers=8) as pool:
    list(pool.map(build, paths))

(OUT / '404.html').write_text(rewrite(fetch('/__missing__/')), encoding='utf-8')
(OUT / 'catalog.js').write_text(fetch('/catalog.js'), encoding='utf-8')
shutil.copytree(ROOT / 'assets', OUT / 'assets')
shutil.copy(ROOT / 'tools/demo-api.js', OUT / 'assets/js/demo-api.js')
shutil.copy(ROOT / 'favicon.ico', OUT / 'favicon.ico')
(OUT / '.nojekyll').write_text('')
(OUT / 'robots.txt').write_text('User-agent: *\nDisallow: /\n')
print(f'Built {OUT}: {len(paths) + 1} pages, base {BASE}')
