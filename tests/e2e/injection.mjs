// proves injected markup cannot run under the enforced CSP. OWNPOST=<id of a post owned by E2E user>
process.env.NORUN = '1'; process.env.LOGIN = '1';
const { page, browser, BASE } = await import('./probe.mjs');
const sleep = ms => new Promise(r => setTimeout(r, ms));
const POST = process.env.OWNPOST;
const res = [];
const reqs = []; page.on('request', r => { if (r.method() !== 'GET') reqs.push(r.method()+' '+r.url().replace(BASE,'')); });
let dialogs = []; let accept = false;
page.on('dialog', async d => { dialogs.push(d.message()); await (accept ? d.accept() : d.dismiss()); });

// 1. Alpine delete modal on an owned post
await page.goto(`${BASE}/@goat`, { waitUntil: 'networkidle2' }); await sleep(2500);
try {
  const opener = await page.$('[x-data*="showDeleteModal"] button');
  if (!opener) throw new Error('no owner delete button on /@goat');
  await opener.click(); await sleep(900);
  const open = await page.$$eval('body > div[role="dialog"]', els => els.some(e => getComputedStyle(e).display !== 'none'));
  res.push([open ? 'PASS':'FAIL', 'Alpine delete-modal opens under enforced CSP']);
} catch (e) { res.push(['FAIL','Alpine delete-modal', e.message]); }

// 2. data-confirm: dismiss blocks, accept allows
await page.goto(BASE + '/', { waitUntil: 'networkidle2' }); await sleep(500);
await page.evaluate(() => {
  document.body.insertAdjacentHTML('beforeend', '<form id="cf" method="post" action="/__confirm_test" data-confirm="Really?"><button>go</button></form>');
  window.__submitted = 0; document.getElementById('cf').addEventListener('submit', e => { window.__submitted++; e.preventDefault(); });
});
await page.evaluate(() => document.getElementById('cf').requestSubmit()); await sleep(400);
const afterDismiss = await page.evaluate(() => window.__submitted);
accept = true;
await page.evaluate(() => document.getElementById('cf').requestSubmit()); await sleep(400);
const afterAccept = await page.evaluate(() => window.__submitted);
res.push([dialogs.length===2 && afterDismiss===0 && afterAccept===1 ? 'PASS':'FAIL', 'data-confirm: dismiss blocks submit, accept lets it through', `dialogs=${dialogs.length} submitted(dismiss)=${afterDismiss} submitted(accept)=${afterAccept}`]);

// 3. allowlist: an injected unknown/dangerous action name does nothing
const bad = await page.evaluate(() => {
  window.__danger = 0; window.dangerous = () => window.__danger++;
  document.body.insertAdjacentHTML('beforeend', '<button id="evil" data-click="dangerous" data-args="[]">x</button><button id="evil2" data-click="alert" data-args="[1]">y</button>');
  document.getElementById('evil').click(); document.getElementById('evil2').click();
  return window.__danger;
});
res.push([bad===0 ? 'PASS':'FAIL', 'markup cannot trigger non-allowlisted functions', 'danger calls=' + bad]);

// 4. HTML injection: inline handlers and javascript: URLs must not run (parser-inserted markup)
const inj = await page.evaluate(async () => {
  window.__inl = 0; window.__jsurl = 0; window.__svg = 0;
  const box = document.createElement('div');
  box.innerHTML = '<img src=x onerror="window.__inl=1"><a id="jsa" href="javascript:window.__jsurl=1">x</a><svg onload="window.__svg=1"></svg><iframe srcdoc="<script>parent.__inl=2<\/script>"></iframe>';
  document.body.appendChild(box);
  document.getElementById('jsa').click();
  await new Promise(r => setTimeout(r, 1000));
  return {inl: window.__inl, jsurl: window.__jsurl, svg: window.__svg,
          blocked: [...new Set(window.__csp.filter(v => v.endsWith('enforce')).map(v => v.split(' | ')[0]))]};
});
res.push([inj.inl===0 && inj.jsurl===0 && inj.svg===0 ? 'PASS':'FAIL', 'injected onerror / javascript: / svg onload do not execute', JSON.stringify(inj)]);

console.log(res.map(r => `${r[0]}  ${r[1]}${r[2] ? '  -> '+r[2] : ''}`).join('\n'));
await browser.close();
