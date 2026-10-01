// export journey: button -> password confirmation -> back to settings -> second press downloads the data
process.env.NORUN = '1'; process.env.LOGIN = '1';
const { page, browser, BASE } = await import('./probe.mjs');
setTimeout(() => { console.error('TIMEOUT'); process.exit(2); }, 120000).unref();
const sleep = ms => new Promise(r => setTimeout(r, ms));
const PASSWORD = process.env.E2E_PASSWORD || 'password';
const res = [];
const exportResponses = [];
page.on('response', r => { if (r.url().endsWith('/profile/export')) exportResponses.push([r.request().method(), r.status(), r.headers()['content-type'] || '', r.headers()['content-disposition'] || '']); });

await page.goto(BASE + '/profile/edit', { waitUntil: 'networkidle2' });
const press = async () => {
  await page.evaluate(() => document.querySelector('form[action$="/profile/export"]').requestSubmit());
  await sleep(1500);
};
await press();
res.push([page.url().includes('/confirm-password') ? 'PASS' : 'FAIL', 'first press asks for the password', page.url().replace(BASE, '')]);

await page.type('input[name="password"]', PASSWORD);
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('form button[type="submit"]')]);
const landed = new URL(page.url()).pathname;
const okPage = await page.evaluate(() => !/405|Method Not Allowed/i.test(document.title + document.body.innerText.slice(0, 300)));
res.push([landed === '/profile/edit' && okPage ? 'PASS' : 'FAIL', 'after confirming, the user lands on the settings page (not a 405)', landed]);

if (!page.url().includes('/profile/edit')) await page.goto(BASE + '/profile/edit', { waitUntil: 'networkidle2' });
// second press: a file download, so watch the network response instead of navigation
await page.evaluate(() => document.querySelector('form[action$="/profile/export"]').requestSubmit());
await sleep(2500);
const last = exportResponses[exportResponses.length - 1] || [];
res.push([last[1] === 200 ? 'PASS' : 'FAIL', 'second press returns the export', `status=${last[1]} type=${last[2].slice(0, 30)} disposition=${last[3].slice(0, 40)}`]);
console.log(res.map(r => `${r[0]}  ${r[1]}  -> ${r[2]}`).join('\n'));
await browser.close();
