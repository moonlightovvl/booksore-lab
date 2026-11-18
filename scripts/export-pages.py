"""Export rendered PHP pages as an explicitly static GitHub Pages demonstration.

Run while the local Apache + PHP + MySQL stack is running.
Only rendered public pages and static assets are copied; no PHP, passwords or feedback.
"""
from pathlib import Path
from urllib.request import urlopen
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
DESTINATION = ROOT / 'docs'
DESTINATION.mkdir(exist_ok=True)
BASE = 'http://127.0.0.1:8080/'
banner = '''<aside class="demo-banner" role="note">Лабораторная №4 · Статическая демонстрация GitHub Pages.
PHP и база данных работают в <a href="http://127.0.0.1:8080/">локальной версии</a>.
<a href="https://github.com/moonlightovvl/booksore-lab">Исходный код и SQL</a>.</aside>'''
pages = {'index.php': 'index.html', 'catalog.php': 'catalog.html', 'order.php': 'order.html'}
pages.update({f'book.php?id={book_id}': f'{book_id}book.html' for book_id in range(3, 9)})

for source, target in pages.items():
    with urlopen(BASE + source) as response:
        text = response.read().decode('utf-8')
    for original, replacement in sorted(pages.items(), key=lambda item: -len(item[0])):
        text = text.replace(original.replace('&', '&amp;'), replacement)
    # No session, CSRF or idempotency tokens in a published static snapshot.
    text = re.sub(r'\s*<input type="hidden"[^>]*>', '', text)
    text = text.replace('<body>', '<body>\n' + banner)
    if source == 'order.php':
        text = text.replace('данные сохраняются в локальную базу. Оплата не производится.',
            'на GitHub Pages отправка отключена. Для сохранения в БД откройте локальную версию.')
        text = re.sub(r'(<form id="order-form"[^>]*>)', r'\1<fieldset disabled class="row g-4 g-lg-5 m-0 p-0 border-0">', text)
        text = text.replace('</form>', '</fieldset></form>')
        text = text.replace('>Отправить заказ</button>', '>Отправка доступна локально</button>')
        text = re.sub(r'<noscript>.*?</noscript>', '', text, flags=re.S)
    (DESTINATION / target).write_text('\n'.join(line.rstrip() for line in text.splitlines()) + '\n', encoding='utf-8')

public = ROOT / 'app' / 'public'
for folder in ['css', 'js', 'images']:
    shutil.copytree(public / folder, DESTINATION / folder, dirs_exist_ok=True)
shutil.copy2(public / 'script.js', DESTINATION / 'script.js')
# Preserve the configured lab-3 analytics only on the online demonstration.
shutil.copy2(ROOT / 'analytics-config.js', DESTINATION / 'analytics-config.js')
old_js = (ROOT / 'script.js').read_text(encoding='utf-8-sig')
marker = '// ID задаётся в analytics-config.js;'
if marker in old_js:
    with (DESTINATION / 'script.js').open('a', encoding='utf-8') as stream:
        stream.write('\n' + marker + old_js.split(marker, 1)[1])
    for page in DESTINATION.glob('*.html'):
        text = page.read_text(encoding='utf-8').replace('<script src="script.js"', '<script src="analytics-config.js" defer></script>\n  <script src="script.js"')
        page.write_text(text, encoding='utf-8')
(DESTINATION / '.nojekyll').touch()
assert not list(DESTINATION.rglob('*.php'))
for page in DESTINATION.glob('*.html'):
    text = page.read_text(encoding='utf-8')
    assert 'name="csrf"' not in text and 'name="requestToken"' not in text
    assert '.php' not in text
print(f'Exported {len(pages)} rendered pages into {DESTINATION}')
