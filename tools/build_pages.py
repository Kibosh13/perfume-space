#!/usr/bin/env python3
"""Static demo build for GitHub Pages.

Renders the PHP pages through a running local server and rewrites paths for a
project site (https://<user>.github.io/<repo>/). Orders work in demo mode via
tools/demo-api.js and are kept only in the visitor's browser.

Usage: php -S 127.0.0.1:8085 -t . router.php &
       python3 tools/build_pages.py /tmp/ps-pages /perfume-space
"""
import re
import shutil
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SERVER = 'http://127.0.0.1:8085'
OUT = Path(sys.argv[1] if len(sys.argv) > 1 else '/tmp/ps-pages')
BASE = (sys.argv[2] if len(sys.argv) > 2 else '/perfume-space').rstrip('/')

PAGES = {'/': 'index.html', '/privacy.php': 'privacy.html', '/__missing__': '404.html'}


def fetch(path: str) -> str:
    try:
        with urllib.request.urlopen(SERVER + path) as r:
            return r.read().decode('utf-8')
    except urllib.error.HTTPError as e:
        return e.read().decode('utf-8')


def rewrite(html: str) -> str:
    html = html.replace('/privacy.php', f'{BASE}/privacy.html')
    html = re.sub(r'(href|src)="/(?!/)', rf'\1="{BASE}/', html)
    html = html.replace('"/assets/', f'"{BASE}/assets/').replace('\\/assets\\/', f'{BASE}/assets/'.replace('/', '\\/'))
    html = html.replace("'/assets/", f"'{BASE}/assets/")
    html = re.sub(r'<meta name="robots" content="[^"]*">', '<meta name="robots" content="noindex, nofollow">', html)
    html = html.replace(f'<script src="{BASE}/assets/js/app.js', f'<script src="{BASE}/assets/js/demo-api.js" defer></script>\n<script src="{BASE}/assets/js/app.js')
    return html


if OUT.exists():
    for item in OUT.iterdir():
        if item.name != '.git':
            shutil.rmtree(item) if item.is_dir() else item.unlink()
OUT.mkdir(parents=True, exist_ok=True)

for path, name in PAGES.items():
    (OUT / name).write_text(rewrite(fetch(path)), encoding='utf-8')

shutil.copytree(ROOT / 'assets', OUT / 'assets')
js = (OUT / 'assets/js/app.js').read_text(encoding='utf-8')
(OUT / 'assets/js/app.js').write_text(js.replace("'/assets/", f"'{BASE}/assets/"), encoding='utf-8')
shutil.copy(ROOT / 'tools/demo-api.js', OUT / 'assets/js/demo-api.js')
shutil.copy(ROOT / 'favicon.ico', OUT / 'favicon.ico')
(OUT / '.nojekyll').write_text('')
(OUT / 'robots.txt').write_text('User-agent: *\nDisallow: /\n')
print(f'Built {OUT} with base {BASE}')
