// CSP + console probe against the local stack. PATHS=/,/login LOGIN=1 node probe.mjs
// needs: npm i, Chrome (CHROME_PATH), stack on BASE (default http://127.0.0.1:8000)
import puppeteer from 'puppeteer-core';
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const paths = (process.env.PATHS || '/,/login').split(',');
const login = process.env.LOGIN === '1';
const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--use-gl=swiftshader'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 900 });
let out = [];
page.on('pageerror', e => out.push('PAGEERROR ' + e.message.slice(0, 150)));
page.on('console', m => { if (m.type() === 'error' && !/WebGL|BadgeCanvas/.test(m.text())) out.push('CONSOLE ' + m.text().slice(0, 150)); });
page.on('response', r => { if (r.status() >= 400) out.push(`HTTP ${r.status()} ${r.request().method()} ${r.url().replace(BASE,'').slice(0,90)}`); });
await page.evaluateOnNewDocument(() => {
  window.__csp = [];
  document.addEventListener('securitypolicyviolation', e => window.__csp.push(
    `${e.effectiveDirective} | ${(e.blockedURI||'').slice(0,70)} | ${(e.sample||'').slice(0,60).replace(/\s+/g,' ')} | ${e.disposition}`));
});
async function doLogin() {
  await page.goto(BASE + '/login', { waitUntil: 'networkidle2', timeout: 40000 });
  await page.type('input[name="login_identifier"]', process.env.E2E_EMAIL || 'admin@goat.local');
  await page.type('input[name="password"]', process.env.E2E_PASSWORD || 'password');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('form button[type="submit"], form input[type="submit"]')]);
}
if (login) await doLogin();
export { page, browser, out, BASE, doLogin };
if (!process.env.NORUN) {
  for (const p of paths) {
    out.length = 0;
    try { await page.goto(BASE + p, { waitUntil: 'networkidle2', timeout: 45000 }); } catch (e) { out.push('NAV ' + e.message.slice(0,100)); }
    await new Promise(r => setTimeout(r, 1500));
    const v = await page.evaluate(() => window.__csp);
    const uniq = [...new Set(v)];
    console.log(`\n=== ${p}  [${(await page.title()).slice(0,40)}] violations: ${v.length} unique: ${uniq.length}`);
    uniq.slice(0, 30).forEach(x => console.log('  CSP   ' + x));
    [...new Set(out)].slice(0, 12).forEach(x => console.log('  ' + x));
  }
  await browser.close();
}
