// the Alpine components (CSP build, no eval) really work: delete modal, AI panel, rating tabs, toast
process.env.NORUN = '1'; process.env.LOGIN = '1';
const { page, browser, BASE } = await import('./probe.mjs');
setTimeout(() => { console.error('TIMEOUT'); process.exit(2); }, 150000).unref();
const sleep = ms => new Promise(r => setTimeout(r, ms));
const res = []; const errors = [];
page.on('pageerror', e => { if (!/^W$/.test(e.message)) errors.push(e.message.slice(0, 100)); });
const step = async (name, fn) => { try { const r = await fn(); res.push(['PASS', name, r || '']); } catch (e) { res.push(['FAIL', name, e.message.slice(0, 160)]); } };
const visible = sel => page.$$eval(sel, els => els.filter(e => getComputedStyle(e).display !== 'none').length);

// --- rating tabs
await page.goto(BASE + '/rating', { waitUntil: 'networkidle2' }); await sleep(900);
await step('rating: loading state ends and first tab is shown', async () => {
  const shimmer = await visible('[x-show="isLoading"]'); const first = await visible('[x-show="isPostVotes"]');
  if (shimmer !== 0 || first !== 1) throw new Error(`shimmer=${shimmer} firstTab=${first}`);
});
for (const [click, panel] of [['showPostCount', 'isPostCount'], ['showCommentLikes', 'isCommentLikes'], ['showCommentCount', 'isCommentCount'], ['showPostVotes', 'isPostVotes']]) {
  await step(`rating: ${click} shows only ${panel}`, async () => {
    await page.click(`[\\@click\\.prevent="${click}"]`); await sleep(250);
    const states = await page.evaluate(() => ['isPostVotes', 'isPostCount', 'isCommentLikes', 'isCommentCount']
      .map(p => [p, getComputedStyle(document.querySelector(`[x-show="${p}"]`)).display !== 'none']));
    const shown = states.filter(s => s[1]).map(s => s[0]);
    if (shown.length !== 1 || shown[0] !== panel) throw new Error('shown: ' + shown.join(','));
    const activeBtn = await page.$eval(`[\\@click\\.prevent="${click}"]`, b => b.className.includes('bg-white dark:bg-gray-600'));
    if (!activeBtn) throw new Error('active tab button not highlighted');
  });
}

// --- AI insight panel + delete modal on a page that has posts with context (owner = profile page)
await page.goto(BASE + (process.env.AI_PAGE || '/@goat'), { waitUntil: 'networkidle2' }); await sleep(1200);
await step('ai panel: component initialised from data-preference', async () => {
  const n = await page.$$eval('[x-data="aiInsight"]', els => els.length);
  if (!n) throw new Error('no aiInsight component on page');
  return n + ' component(s)';
});
await step('ai panel: toggle button flips visibility and button state', async () => {
  const root = await page.$('[x-data="aiInsight"]:has([\\@click="togglePanel"])');
  if (!root) return 'n/a (no post with AI context on this page)';
  const panelSel = '[x-show="isPanelVisible"]';
  const before = await root.$eval(panelSel, e => getComputedStyle(e).display !== 'none');
  await (await root.$('[\\@click="togglePanel"]')).click(); await sleep(500);
  const after = await root.$eval(panelSel, e => getComputedStyle(e).display !== 'none');
  const title = await root.$eval('[\\@click="togglePanel"]', b => b.getAttribute('title'));
  if (before === after) throw new Error('panel did not toggle');
  return `${before}->${after}, title="${title.slice(0, 18)}…"`;
});
await step('ai panel: Show more / Show less label and height class', async () => {
  const root = await page.$('[x-data="aiInsight"]:has([\\@click="toggleExpanded"])');
  if (!root) return 'n/a';
  const btn = await root.$('[\\@click="toggleExpanded"]');
  const panel = await root.$('[x-show="isPanelVisible"]');
  if (!(await panel.evaluate(e => getComputedStyle(e).display !== 'none'))) await (await root.$('[\\@click="togglePanel"]')).click();
  await sleep(500);
  const t0 = await btn.$eval('span', s => s.textContent.trim());
  await btn.click(); await sleep(300);
  const t1 = await btn.$eval('span', s => s.textContent.trim());
  const cls = await root.$eval('[class*="max-h-"]', e => e.className);
  if (t0 === t1) throw new Error(`label did not change: ${t0}`);
  return `"${t0}" -> "${t1}" (${cls.includes('max-h-screen') ? 'expanded' : 'collapsed'})`;
});
await step('delete modal: open and close (teleported, Alpine CSP build)', async () => {
  const opener = await page.$('[x-data="deleteModal"] button');
  if (!opener) return 'n/a (no owned post on this page)';
  await opener.click(); await sleep(700);
  const open = await visible('body > div[role="dialog"]');
  if (open !== 1) throw new Error('modal not open: ' + open);
  const cancel = await page.evaluateHandle(() => [...document.querySelectorAll('body > div[role="dialog"]')]
    .find(d => getComputedStyle(d).display !== 'none').querySelector('button[\\@click="close"]'));
  await cancel.asElement().click(); await sleep(700);
  const closed = await visible('body > div[role="dialog"]');
  if (closed !== 0) throw new Error('modal still open');
});

// --- toast (plain DOM now)
await step('toast: showToast displays then hides', async () => {
  await page.evaluate(() => window.showToast('Alpine-free toast'));
  await sleep(300);
  const shown = await page.$eval('#global-toast', e => getComputedStyle(e).display !== 'none' && e.textContent.includes('Alpine-free toast'));
  if (!shown) throw new Error('toast not shown');
  await sleep(4200);
  const hidden = await page.$eval('#global-toast', e => getComputedStyle(e).display === 'none');
  if (!hidden) throw new Error('toast did not hide');
});

const enforced = await page.evaluate(() => window.__csp.filter(v => v.endsWith('enforce')));
res.push([enforced.length || errors.length ? 'FAIL' : 'PASS', 'no enforced CSP violations / page errors during the run', JSON.stringify({ csp: enforced.slice(0, 3), errors: errors.slice(0, 3) })]);
console.log(res.map(r => `${r[0]}  ${r[1]}${r[2] ? '  -> ' + r[2] : ''}`).join('\n'));
console.log(`\n${res.filter(r => r[0] === 'PASS').length}/${res.length} passed`);
await browser.close();
