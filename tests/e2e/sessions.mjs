// two browsers, same account: "log out other devices" must kill the other one (Redis sessions)
import puppeteer from 'puppeteer-core';
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const EMAIL = process.env.E2E_EMAIL || 'admin@goat.local', PASSWORD = process.env.E2E_PASSWORD || 'password';
// watchdog: never hang CI
setTimeout(() => { console.error('TIMEOUT: still running after 150s'); process.exit(2); }, 150000).unref();
const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: 'new', args: ['--no-sandbox', '--disable-gpu'],
});
const sleep = ms => new Promise(r => setTimeout(r, ms));
const log = m => console.error('  ..', m);
async function loggedInPage() {
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  log('open login'); await page.goto(BASE + '/login', { waitUntil: 'networkidle2' });
  await page.type('input[name="login_identifier"]', EMAIL);
  await page.type('input[name="password"]', PASSWORD);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('form button[type="submit"]')]);
  return page;
}
const authed = async p => { await p.goto(BASE + '/notifications', { waitUntil: 'networkidle2' }); return !p.url().includes('/login'); };
const out = [];
const B = await loggedInPage(); await sleep(1100);
const A = await loggedInPage();
out.push([await authed(B) && await authed(A) ? 'PASS' : 'FAIL', 'both devices logged in']);

// A confirms password, then terminates all other sessions
await A.goto(BASE + '/confirm-password', { waitUntil: 'networkidle2' });
await A.type('input[name="password"]', PASSWORD);
await Promise.all([A.waitForNavigation({ waitUntil: 'networkidle2' }), A.click('form button[type="submit"]')]);
const status = await A.evaluate(async () => {
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const r = await fetch('/profile/sessions/terminate-all', { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, redirect: 'manual' });
  return r.type + ':' + r.status;
});
await sleep(800);
out.push([['opaqueredirect:0', 'basic:302', 'basic:200'].includes(status) || status.startsWith('opaque') ? 'PASS' : 'FAIL', 'terminate-all request accepted', status]);
out.push([await authed(A) ? 'PASS' : 'FAIL', 'device A (initiator) is still signed in']);
out.push([!(await authed(B)) ? 'PASS' : 'FAIL', 'device B was signed out']);
console.log(out.map(r => `${r[0]}  ${r[1]}${r[2] ? '  -> ' + r[2] : ''}`).join('\n'));
await browser.close();
