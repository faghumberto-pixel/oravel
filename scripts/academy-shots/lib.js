const { chromium } = require('/home/oravel/.npm/_npx/e41f203b7505f1fb/node_modules/playwright-core');
exports.open = async (w = 1366, h = 820) => {
  const browser = await chromium.launch({ executablePath: '/home/oravel/.cache/ms-playwright/chromium-1243/chrome-linux64/chrome', args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, locale: 'pt-BR' });
  const page = await ctx.newPage();
  await page.goto('http://127.0.0.1:8099/admin/login', { waitUntil: 'networkidle' });
  await page.fill('input[type=email]', 'admin@oravel.com.br');
  await page.fill('input[type=password]', 'demo12345');
  await Promise.all([page.waitForNavigation({ timeout: 20000 }).catch(() => null), page.keyboard.press('Enter')]);
  await page.waitForTimeout(2500);
  // dispensa o aviso do topo (é do banco clonado)
  await page.locator('text=Dispensar').first().click({ timeout: 1500 }).catch(() => {});
  return { browser, page };
};
exports.go = async (page, path, wait = 2500) => {
  await page.goto('http://127.0.0.1:8099' + path, { waitUntil: 'networkidle' });
  await page.waitForTimeout(wait);
  await page.locator('text=Dispensar').first().click({ timeout: 800 }).catch(() => {});
};
