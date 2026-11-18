const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { chromium } = require(process.env.CICADA_PLAYWRIGHT || 'playwright');

(async () => {
  const root = path.resolve(__dirname, '..');
  const docs = path.join(root, 'docs');
  const mime = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };
  const server = http.createServer((req, res) => {
    const pathname = decodeURIComponent(new URL(req.url, 'http://127.0.0.1').pathname);
    const file = path.resolve(docs, '.' + (pathname === '/' ? '/index.html' : pathname));
    if (!file.startsWith(docs + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { res.writeHead(404); return res.end(); }
    res.writeHead(200, { 'Content-Type': mime[path.extname(file)] || 'application/octet-stream' });
    fs.createReadStream(file).pipe(res);
  });
  await new Promise(resolve => server.listen(8090, '127.0.0.1', resolve));
  let browser;
  try {
    browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await context.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1' ? route.continue() : route.abort());
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let references = 0;
    const pages = fs.readdirSync(docs).filter(name => name.endsWith('.html'));
    for (const name of pages) {
      await page.goto('http://127.0.0.1:8090/' + name, { waitUntil: 'networkidle' });
      if (await page.locator('.demo-banner').count() !== 1) throw new Error('Static demonstration notice missing: ' + name);
      const urls = await page.locator('[href],[src]').evaluateAll(elements => elements.map(element => element.getAttribute('href') || element.getAttribute('src')));
      for (const url of urls) {
        if (!url || /^(https?:|mailto:|data:|#)/.test(url)) continue;
        const file = path.resolve(docs, decodeURIComponent(url.split(/[?#]/)[0]));
        if (!file.startsWith(docs + path.sep) || !fs.existsSync(file)) throw new Error('Broken published reference: ' + name + ' -> ' + url);
        references++;
      }
      if (await page.locator('img').evaluateAll(images => images.some(image => !image.complete || image.naturalWidth === 0))) throw new Error('Broken image: ' + name);
    }
    await page.goto('http://127.0.0.1:8090/order.html', { waitUntil: 'networkidle' });
    if (!(await page.locator('#submit-order').isDisabled())) throw new Error('Published static form must be disabled');
    if (await page.locator('input[type=hidden]').count()) throw new Error('Session tokens found in static export');
    await page.screenshot({ path: path.join(root, 'runtime/screenshots/pages-order-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) throw new Error('Static form overflows mobile viewport');
    await page.screenshot({ path: path.join(root, 'runtime/screenshots/pages-order-mobile.png'), fullPage: true });
    if (errors.length) throw new Error(JSON.stringify(errors));
    const report = { pages: pages.length, localReferencesChecked: references, imagesLoaded: true, staticFormDisabled: true, sessionTokensExcluded: true, mobile: true, pageErrors: errors };
    fs.writeFileSync(path.join(root, 'runtime/pages-results.json'), JSON.stringify(report, null, 2));
    console.log('Static Pages checks passed:', JSON.stringify(report));
  } finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
  }
})().catch(error => { console.error(error); process.exit(1); });
