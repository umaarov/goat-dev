// screenshots of rendered emails: node mail-shots.mjs <dir with .html files>  -> <dir>/<name>-<light|dark>-<desktop|mobile>.png
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';

const dir = process.argv[2];
const only = process.argv[3];
const browser = await puppeteer.launch({
  executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: true, args: ['--no-sandbox'],
});
for (const file of fs.readdirSync(dir).filter(f => f.endsWith('.html') && (!only || f.startsWith(only)))) {
  for (const scheme of ['light', 'dark']) {
    for (const [label, width] of [['desktop', 760], ['mobile', 375]]) {
      const page = await browser.newPage();
      await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: scheme }]);
      await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
      await page.setRequestInterception(true);
      // remote images are fetched, scripts are never run in an email
      page.on('request', r => (r.resourceType() === 'script' ? r.abort() : r.continue()));
      await page.goto(`file://${path.resolve(dir, file)}`, { waitUntil: 'networkidle2', timeout: 30000 })
        .catch(e => process.stderr.write(`${file}: ${e.message}\n`));
      const out = path.join(dir, `${file.replace('.html', '')}-${scheme}-${label}.png`);
      await page.screenshot({ path: out, fullPage: true });
      await page.close();
    }
  }
}
await browser.close();
process.stdout.write('done\n');
