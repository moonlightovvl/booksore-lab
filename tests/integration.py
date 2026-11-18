from pathlib import Path
import http.cookiejar
import urllib.request
import urllib.parse
import urllib.error
import re
import json
import subprocess
import uuid
import html

ROOT = Path(__file__).resolve().parents[1]
settings = json.loads((ROOT / 'runtime/settings.json').read_text(encoding='utf-8-sig'))
BASE = 'http://127.0.0.1:8080/'
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
checks = []

def request(path, data=None):
    try:
        response = opener.open(BASE + path, None if data is None else urllib.parse.urlencode(data).encode(), timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.read().decode('utf-8'), response.headers

def check(condition, label):
    if not condition:
        raise AssertionError(label)
    checks.append(label)
    print('PASS:', label, flush=True)

def hidden(text, name):
    return re.search(r'name="' + name + r'" value="([^"]+)"', text).group(1)

def driver(action, token):
    process = subprocess.run([settings['php'], '-c', settings['phpIni'], str(ROOT/'tests/db-driver.php'), action, token], capture_output=True, check=True)
    return json.loads(process.stdout.decode('utf-8-sig'))

code, catalog, headers = request('catalog.php')
check(code == 200 and catalog.count('data-book-id=') == 6, 'Catalog renders six books from MySQL')
check('no-store' in headers['Cache-Control'], 'Dynamic HTML disables stale browser caching')
code, dbpage, _ = request('database.php')
csrf = hidden(dbpage, 'csrf')
original_title = html.unescape(re.search(r'id="title-7"[^>]*value="([^"]*)"', dbpage).group(1))
original_price = re.search(r'id="price-7"[^>]*value="([^"]*)"', dbpage).group(1)
test_title = '1984 <script>alert("test")</script>'
tokens = []
try:
    code, _, _ = request('database.php', {'csrf':csrf, 'id':7, 'title':test_title, 'price':'999.99', 'action':'save'})
    check(code == 200, 'Database editor executes a real UPDATE')
    for page in ['catalog.php', 'book.php?id=7', 'order.php', 'index.php']:
        code, text, _ = request(page)
        check(code == 200 and '999,99' in text and '&lt;script&gt;' in text and '<script>alert(' not in text, f'Changed DB data appears and is escaped: {page}')
    code, form, _ = request('order.php?book=7')
    token = hidden(form, 'requestToken')
    tokens.append(token)
    data = {'csrf': hidden(form,'csrf'), 'requestToken':token, 'book':7, 'quantity':2,
            'customerName':'Проверка защиты', 'email':f'lab4-integration-{uuid.uuid4().hex}@example.test',
            'phone':'+7 (999) 123-45-67', 'delivery':'courier', 'address':'Учебный адрес 123', 'message':'Проверка сохранения UTF-8',
            'price':'0.01', 'total':'0.02'}
    code, saved, _ = request('order.php', data)
    check(code == 200 and 'Данные записаны в базу данных' in saved, 'HTML form POST saves successfully without JavaScript')
    rows = driver('read', token)
    check(len(rows) == 1 and rows[0]['unit_price'] == '999.99' and rows[0]['total'] == '1999.98' and rows[0]['message'] == data['message'], 'Actual persisted row, server price and DECIMAL total verified')
    request('order.php', data)
    request('order.php?saved=1')
    check(len(driver('read', token)) == 1, 'POST replay and GET refresh do not duplicate feedback')
    invalid = data | {'quantity':'0','email':'bad-email','book':"7 OR 1=1"}
    code, _, _ = request('order.php', invalid)
    check(code == 422 and len(driver('read', token)) == 1, 'Invalid fields and SQL injection are rejected')
    code, _, _ = request('order.php', data | {'csrf':'wrong'})
    check(code == 403, 'Forged CSRF token is rejected')
    malformed = dict(data)
    del malformed['customerName']
    malformed['customerName[]'] = 'array attack'
    code, _, _ = request('order.php', malformed)
    check(code == 422, 'Malformed array field is rejected without a server error')
    request('database.php', {'csrf':csrf, 'id':7, 'title':original_title, 'price':original_price, 'action':'save'})
    for page in ['catalog.php', 'book.php?id=7', 'order.php', 'index.php']:
        code, text, _ = request(page)
        check(code == 200 and '999,99' not in text and '&lt;script&gt;' not in text, f'Original DB data restored on site: {page}')
    check(driver('read', token)[0]['unit_price'] == '999.99', 'Existing order retains the price at submission')
    check(request('book.php?id=999999')[0] == 404, 'Missing book returns HTTP 404')
    check(request('config.local.php')[0] == 404 and request('../config.local.php')[0] in [400,403,404], 'Private PHP configuration is outside Apache DocumentRoot; traversal is denied')
finally:
    request('database.php', {'csrf':csrf, 'id':7, 'title':original_title, 'price':original_price, 'action':'save'})
    for token in tokens:
        driver('cleanup', token)
report = {'passed':len(checks), 'checks':checks, 'book_7_restored':True, 'test_feedback_removed':True}
(ROOT/'runtime/integration-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
print('ALL CHECKS PASSED:', len(checks), flush=True)
