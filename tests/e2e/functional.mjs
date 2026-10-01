// logged-in functional run of the CSP-safe handlers + XSS payload through the real renderer.
// VOTE_POST must be a post the user has not voted on; COMMENT_POST one with comments.
process.env.NORUN = '1';
process.env.LOGIN = '1';
const { page, browser, BASE } = await import('./probe.mjs');
const results = [];
const step = async (name, fn) => { try { const r = await fn(); results.push(['PASS', name, r || '']); } catch (e) { results.push(['FAIL', name, e.message.slice(0, 200)]); } };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const reqs = [];
page.on('request', r => { if (['POST','PUT','DELETE'].includes(r.method())) reqs.push(r.method() + ' ' + r.url().replace(BASE, '').split('?')[0]); });
const violations = () => page.evaluate(() => window.__csp.filter(v => v.endsWith('enforce')));
page.on('dialog', async d => { page.__lastDialog = d.message(); await (page.__dialogAccept === false ? d.dismiss() : d.accept()); });

await step('login works, lands authenticated', async () => {
  const ok = await page.evaluate(() => document.body.dataset.userIsAuthenticated);
  if (ok !== 'true') throw new Error('not authenticated: ' + ok);
});

await page.goto(BASE + (process.env.VOTE_POST || '/@AnakinSkywalker/post/33'), { waitUntil: 'networkidle2' });
await sleep(800);

await step('vote handler (data-click voteForOption) sends the vote', async () => {
  const before = reqs.length;
  const btn = await page.$('[data-click="voteForOption"]');
  if (!btn) throw new Error('no vote button found');
  await btn.click(); await sleep(1500);
  const sent = reqs.slice(before).filter(r => /\/posts\/\d+\/vote/.test(r));
  if (!sent.length) throw new Error('no vote request; got ' + JSON.stringify(reqs.slice(before)));
  return sent[0];
});

await page.goto(BASE + (process.env.COMMENT_POST || '/@asadbek/post/1'), { waitUntil: 'networkidle2' });
await sleep(800);

await step('toggleComments opens the comments section', async () => {
  const btn = await page.$('[data-click="toggleComments"]');
  if (!btn) throw new Error('no toggle button');
  await btn.click(); await sleep(2500);
  const n = await page.$$eval('.comments-list .comment, .comments-list [id^="comment-"]', els => els.length);
  if (!n) throw new Error('no comments rendered');
  return n + ' comments rendered';
});

await step('STORED XSS payload through the real createCommentElement renders inert', async () => {
  const out = await page.evaluate(() => {
    window.__pwned = 0;
    const payload = {
      id: 99999, content: '<img src=x onerror="window.__pwned=1"> </span><script>window.__pwned=2<\/script> http://example.com/a?b=1&c=2 javascript:alert(3) @admin',
      likes_count: 0, created_at: new Date().toISOString(), parent_id: null, root_comment_id: null, replies_count: 0, flat_replies: [],
      user: { id: 2, username: 'x"><img src=y onerror="window.__pwned=3">', profile_picture: 'javascript:alert(4)' },
    };
    const el = createCommentElement(payload, 1, false);
    document.body.appendChild(el);
    return new Promise(res => setTimeout(() => res({
      pwned: window.__pwned,
      liveImgWithHandler: !!el.querySelector('img[onerror]'),
      liveScript: !!el.querySelector('script'),
      escapedText: el.innerHTML.includes('&lt;img'),
      linkAttrs: [...el.querySelectorAll('a[href^="http://example.com"]')].map(a => a.rel + '|' + a.target)[0] || 'none',
      mention: !!el.querySelector('a[href="/@admin"]'),
      avatarSrc: (el.querySelector('img')||{}).src,
      anyJsHref: [...el.querySelectorAll('[href]')].some(a => /^javascript:/i.test(a.getAttribute('href'))),
    }), 800));
  });
  if (out.pwned) throw new Error('XSS EXECUTED: ' + out.pwned);
  if (out.liveImgWithHandler) throw new Error('img with onerror in DOM');
  if (out.liveScript) throw new Error('script element in DOM');
  if (out.anyJsHref) throw new Error('javascript: href in DOM');
  if (!out.mention) throw new Error('mention not linked');
  return JSON.stringify(out);
});

await step('reply button (prepareReply via data-click) sets reply state', async () => {
  const btn = await page.$('.comments-list [data-click="prepareReply"]');
  if (!btn) throw new Error('no reply button');
  await btn.click(); await sleep(500);
  const txt = await page.$eval('[id^="reply-indicator-"]', e => e.textContent + '|' + e.className);
  if (!/Replying to @/.test(txt)) throw new Error('indicator: ' + txt);
  return txt.slice(0, 40);
});

await step('cancelReply clears reply state', async () => {
  const btn = await page.$('[data-click="cancelReply"]');
  await btn.click(); await sleep(400);
  const hidden = await page.$eval('[id^="reply-indicator-"]', e => e.classList.contains('hidden'));
  if (!hidden) throw new Error('indicator still visible');
});

await step('submitComment (data-submit) posts a comment and renders it', async () => {
  const before = reqs.length;
  await page.type('form[data-submit="submitComment"] textarea[name="content"]', 'Browser test comment ' + Date.now());
  await page.click('form[data-submit="submitComment"] button[type="submit"]');
  await sleep(3500);
  const sent = reqs.slice(before).filter(r => /comments/.test(r));
  if (!sent.length) throw new Error('no comment request');
  return sent[0];
});

await step('like handler (toggleCommentLike) fires', async () => {
  const before = reqs.length;
  const btn = await page.$('.comments-list [data-click="toggleCommentLike"]');
  if (!btn) throw new Error('no like button');
  await btn.click(); await sleep(1500);
  const sent = reqs.slice(before).filter(r => /toggle-like|like/.test(r));
  if (!sent.length) throw new Error('no like request: ' + JSON.stringify(reqs.slice(before)));
  return sent[0];
});

await step('delete (data-submit deleteComment) removes own comment', async () => {
  const before = reqs.length;
  const form = await page.$('.comments-list form[data-submit="deleteComment"]');
  if (!form) throw new Error('no delete form');
  await form.$eval('button', b => b.click()); await sleep(2500);
  const sent = reqs.slice(before).filter(r => r.startsWith('DELETE') || /comments/.test(r));
  if (!sent.length) throw new Error('no delete request: ' + JSON.stringify(reqs.slice(before)));
  return sent[0];
});

await step('sharePost handler fires', async () => {
  const before = reqs.length;
  const btn = await page.$('[data-click="sharePost"]');
  if (!btn) throw new Error('no share button');
  await btn.click(); await sleep(1200);
  return JSON.stringify(reqs.slice(before));
});

await page.goto(BASE + '/profile/edit', { waitUntil: 'networkidle2' });
await sleep(800);

await step('data-confirm form: dismissing the dialog blocks submit', async () => {
  const before = reqs.length;
  page.__dialogAccept = false;
  const form = await page.$('form[data-confirm]');
  if (!form) return 'n/a (no confirm form visible)';
  await form.evaluate(f => f.requestSubmit());
  await sleep(800);
  page.__dialogAccept = true;
  if (!page.__lastDialog) throw new Error('no confirm dialog shown');
  if (reqs.length !== before) throw new Error('form submitted despite dismiss: ' + JSON.stringify(reqs.slice(before)));
  return 'dialog: ' + page.__lastDialog.slice(0, 40);
});

await step('updateDynamicLinkIcon (data-input) reacts to typing', async () => {
  const input = await page.$('input[data-input="updateDynamicLinkIcon"]');
  if (!input) throw new Error('no link input');
  await input.click({ clickCount: 3 }); await input.type('https://github.com/umaarov');
  await sleep(400);
  return 'typed without error';
});

await page.goto(BASE + '/posts/create', { waitUntil: 'networkidle2' });
await sleep(800);
await step('image cropper opens from data-change on file input', async () => {
  const input = await page.$('input[type="file"][data-change="openImageCropper"]');
  if (!input) throw new Error('no file input');
  const fs = await import('node:fs');
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAIAAAD/gAIDAAAAYElEQVR4nO3PQQ0AAAgEIL9/6HYwhwUIdLbtdL1ABBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQUBBQEFAQU/BYA6P0Bd5ztLBYAAAAASUVORK5CYII=', 'base64');
  fs.writeFileSync('/tmp/_probe.png', png);
  await input.uploadFile('/tmp/_probe.png'); await sleep(1500);
  const shown = await page.evaluate(() => { const m = document.getElementById('imageCropModalGlobal'); return !!m && !m.classList.contains('hidden'); });
  if (!shown) throw new Error('cropper modal not shown');
});

await page.goto(BASE + '/rating', { waitUntil: 'networkidle2' });
await sleep(900);
await step('Alpine tabs on /rating switch', async () => {
  const tabs = await page.$$('button[\\@click\\.prevent], button[x-on\\:click\\.prevent]');
  if (tabs.length < 2) throw new Error('tabs not found: ' + tabs.length);
  await tabs[1].click(); await sleep(500);
});

await page.goto(BASE + '/login', { waitUntil: 'networkidle2' }).catch(()=>{});
const enforced = await violations();
results.push([enforced.length ? 'FAIL' : 'PASS', 'no ENFORCED CSP violations on last page', JSON.stringify([...new Set(enforced)].slice(0,5))]);

console.log('\n' + results.map(r => `${r[0]}  ${r[1]}${r[2] ? '  -> ' + r[2] : ''}`).join('\n'));
console.log(`\n${results.filter(r => r[0]==='PASS').length}/${results.length} passed`);
await browser.close();
