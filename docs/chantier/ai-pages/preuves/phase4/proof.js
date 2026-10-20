/*
 * Browser proof of the content screen: log in, create a page, write it from
 * brief 01, watch the progress, publish, read the front, run axe on the
 * screen and on the published page. Screenshots and the axe reports go to
 * the directory given as first argument.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const base = 'https://thelia-cms-ai.ddev.site';
const out = process.argv[2];
const brief = fs.readFileSync(process.argv[3], 'utf8').trim();
const axeSource = fs.readFileSync(path.join(__dirname, 'node_modules/axe-core/axe.min.js'), 'utf8');

async function axe(page, name) {
    await page.addScriptTag({ content: axeSource });
    const results = await page.evaluate(async () => await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa'] }));
    const violations = results.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 5).map(n => n.target.join(' ')) }));
    fs.writeFileSync(path.join(out, `axe-${name}.json`), JSON.stringify(violations, null, 2));
    console.log(`axe ${name}: ${violations.length} violation(s)` + (violations.length ? ' ' + violations.map(v => v.id + '×' + v.nodes.length).join(', ') : ''));
}

(async () => {
    fs.mkdirSync(out, { recursive: true });
    const browser = await chromium.launch();
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
    const page = await context.newPage();
    const log = [];
    page.on('console', m => { if (m.type() === 'error') log.push('console: ' + m.text()); });
    page.on('pageerror', e => log.push('pageerror: ' + e.message));

    await page.goto(base + '/admin/login');
    await page.fill('[data-testid=login-username]', process.env.PROOF_LOGIN);
    await page.fill('[data-testid=login-password]', process.env.PROOF_PASSWORD);
    await Promise.all([page.waitForURL(/\/admin(?!\/login)/), page.click('[data-testid=login-submit]')]);
    console.log('logged in: ' + page.url());

    // A new page, from the ordinary form.
    await page.goto(base + '/admin/cms/pages/new');
    await page.fill('input[name="cms_page[title]"]', 'Studios photo à louer (preuve phase 4)');
    await Promise.all([page.waitForURL(/\/admin\/cms\/pages\/\d+/), page.click('form[name=cms_page] button[type=submit]')]);
    const pageId = page.url().match(/\/pages\/(\d+)/)[1];
    console.log('page created: #' + pageId);

    await page.goto(base + `/admin/cms/pages/${pageId}/content`);
    await page.screenshot({ path: path.join(out, '01-empty.png'), fullPage: false });
    await axe(page, 'screen-empty');

    // The brief, posted in the background; the progress is shown as it runs.
    await page.fill('textarea[name="cms_brief[text]"]', brief);
    await page.fill('input[name="cms_brief[tone]"]', 'direct, vouvoiement');
    const started = Date.now();
    await page.click('[data-testid=cms-content-generate]');
    await page.waitForSelector('[data-cms-progress]:not([hidden])', { timeout: 5000 });
    await page.waitForSelector('[data-cms-progress-steps] li', { timeout: 15000 });
    await page.screenshot({ path: path.join(out, '02-progress.png') });
    const steps = [];
    await page.waitForURL(/done=\d+/, { timeout: 240000 });
    console.log(`generated in ${((Date.now() - started) / 1000).toFixed(1)} s: ` + page.url());
    await page.waitForSelector('[data-testid=cms-content-frame]');
    await page.waitForTimeout(2500);
    await page.screenshot({ path: path.join(out, '03-written.png') });
    const toast = await page.textContent('.cms-toast');
    console.log('outcome: ' + toast.trim().replace(/\s+/g, ' '));
    const sections = await page.$$eval('[data-testid=cms-content-sections] li', items => items.map(i => i.dataset.section + ' (' + i.querySelector('code').textContent + ')'));
    console.log('sections: ' + sections.join(', '));
    await axe(page, 'screen-written');

    // One section written again.
    const first = sections[0].split(' ')[0];
    await page.click(`li[data-section="${first}"] summary`);
    await page.fill(`li[data-section="${first}"] textarea`, 'Plus court : une phrase.');
    const before = page.url();
    await page.click(`li[data-section="${first}"] button[type=submit]`);
    // A different URL, not a pattern the current one already matches.
    await page.waitForURL(url => url.toString() !== before && /done=\d+/.test(url.toString()), { timeout: 240000 });
    await page.waitForTimeout(500);
    console.log('section rewritten: ' + (await page.textContent('.cms-toast')).trim().replace(/\s+/g, ' '));
    await page.screenshot({ path: path.join(out, '04-section.png') });

    // Publish, then read the front.
    await Promise.all([page.waitForNavigation(), page.click('[data-testid=cms-content-publish] button')]);
    console.log('published: ' + (await page.textContent('.cms-toast')).trim().replace(/\s+/g, ' '));
    const front = await page.getAttribute('[data-testid=cms-page-preview-btn]', 'href');
    await page.goto(front);
    console.log('front: ' + front + ' -> ' + (await page.title()));
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(out, '05-front.png'), fullPage: true });
    await axe(page, 'front');
    const h1 = await page.$$eval('h1', hs => hs.map(h => h.textContent.trim()));
    console.log('h1: ' + JSON.stringify(h1));

    fs.writeFileSync(path.join(out, 'browser-log.txt'), log.join('\n'));
    console.log('browser errors: ' + log.length);
    await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
