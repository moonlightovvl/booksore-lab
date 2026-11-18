const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.CICADA_PLAYWRIGHT || 'playwright');

(async () => {
  const root = path.resolve(__dirname, '..');
  const screenshots = path.join(root, 'runtime', 'screenshots');
  fs.mkdirSync(screenshots, { recursive: true });
  const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const externalRequests = [];
  const errors = [];
  await context.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.hostname !== '127.0.0.1') { externalRequests.push(url.href); return route.abort(); }
    return route.continue();
  });
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  const base = 'http://127.0.0.1:8080/';
  for (const [url, file] of [['index.php', 'home-desktop.png'], ['catalog.php', 'catalog-original.png'], ['book.php?id=7', 'book-desktop.png'], ['order.php?book=7', 'order-desktop.png'], ['database.php', 'database-desktop.png']]) {
    await page.goto(base + url, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(screenshots, file), fullPage: true });
  }
  const originalPrice = await page.locator('#price-7').inputValue();
  try {
    await page.locator('#price-7').fill('999.00');
    await page.locator('button[form="book-7"][value="save"]').click();
    await page.waitForLoadState('networkidle');
    await page.goto(base + 'catalog.php', { waitUntil: 'networkidle' });
    if (!(await page.locator('[data-book-id="7"] .book-price').textContent()).includes('999,00')) throw new Error('Changed price missing in browser');
    await page.screenshot({ path: path.join(screenshots, 'catalog-changed-999.png'), fullPage: true });
  } finally {
    await page.goto(base + 'database.php');
    await page.locator('#price-7').fill(originalPrice);
    await page.locator('button[form="book-7"][value="save"]').click();
    await page.waitForLoadState('networkidle');
  }
  await page.goto(base + 'catalog.php', { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(screenshots, 'catalog-restored-760.png'), fullPage: true });
  await page.goto(base + 'catalog.php');
  await page.locator('#search').fill('Оруэлл');
  if (await page.locator('.book-item:visible').count() !== 1) throw new Error('Catalog search failed');
  await page.locator('#search').fill('');
  await page.locator('#genre').selectOption({ label: 'Ужасы' });
  if (await page.locator('.book-item:visible').count() !== 2) throw new Error('Genre filter failed');
  await page.goto(base + 'order.php?book=7');
  await page.locator('#quantity').fill('2');
  if (!(await page.locator('#order-total').textContent()).replaceAll('\u00a0', ' ').includes('1 520,00')) throw new Error('Order total update failed');
  await page.locator('#delivery').selectOption('courier');
  if (!(await page.locator('#address').isVisible()) || !(await page.locator('#address').getAttribute('required') !== null)) throw new Error('Delivery address toggle failed');
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
  await mobile.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1' ? route.continue() : route.abort());
  const mobilePage = await mobile.newPage();
  for (const [url, file] of [['catalog.php', 'catalog-mobile.png'], ['order.php?book=7', 'order-mobile.png']]) {
    await mobilePage.goto(base + url, { waitUntil: 'networkidle' });
    if (await mobilePage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)) throw new Error('Horizontal overflow on mobile: ' + url);
    await mobilePage.screenshot({ path: path.join(screenshots, file), fullPage: true });
  }
  const noJs = await browser.newContext({ javaScriptEnabled: false });
  const noJsPage = await noJs.newPage();
  await noJsPage.goto(base + 'order.php?book=7');
  if (await noJsPage.locator('#submit-order').isDisabled()) throw new Error('Form is disabled without JavaScript');
  if (externalRequests.length || errors.length) throw new Error(JSON.stringify({ externalRequests, errors }));
  const report = { desktop: true, mobile: true, filters: true, orderSummary: true, deliveryToggle: true, formEnabledWithoutJavaScript: true, externalRequests, pageErrors: errors };
  fs.writeFileSync(path.join(root, 'runtime', 'browser-results.json'), JSON.stringify(report, null, 2));
  await browser.close();
  console.log('Browser checks passed:', JSON.stringify(report));
})().catch(error => { console.error(error); process.exit(1); });
