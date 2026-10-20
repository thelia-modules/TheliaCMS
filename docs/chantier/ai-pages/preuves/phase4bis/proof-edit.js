/*
 * Browser proof of editing by hand: open the hero of a page, check the
 * rich-text editor mounted, correct a word, save, publish, read it on the
 * front without any generation. Screenshots and axe report go to the
 * directory given as first argument; the page id is the second.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const base = 'https://thelia-cms-ai.ddev.site';
const out = process.argv[2];
const pageId = process.argv[3];
const axeSource = fs.readFileSync(path.join(__dirname, 'node_modules/axe-core/axe.min.js'), 'utf8');

async function axe(page, name) {
    await page.addScriptTag({ content: axeSource });
    const results = await page.evaluate(async () => await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa'] }));
    const violations = results.violations.filter(v => !v.nodes.every(n => n.target.join(' ').includes('sf-toolbar')));
    fs.writeFileSync(path.join(out, `axe-${name}.json`), JSON.stringify(violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.map(n => n.target.join(' ')) })), null, 2));
    console.log(`axe ${name}: ${violations.length} violation(s) outside the debug toolbar` + (violations.length ? ' ' + violations.map(v => v.id + ': ' + v.nodes.map(n => n.target.join(' ')).join(' | ')).join('; ') : ''));
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

    await page.goto(base + `/admin/cms/pages/${pageId}/content`);
    const tiptapLoaded = await page.evaluate(() => typeof window.TiptapEditor !== 'undefined');
    console.log('Tiptap bundle loaded: ' + tiptapLoaded);

    // The FAQ holds html props: its panel mounts the editor.
    const faq = page.locator('[data-testid=cms-section-edit-questions]');
    await faq.locator('summary').click();
    await page.waitForTimeout(1500);
    const editors = await faq.locator('.ProseMirror, .tiptap').count();
    console.log('rich-text editors mounted in the FAQ panel: ' + editors);
    await faq.scrollIntoViewIfNeeded();
    await page.screenshot({ path: path.join(out, '06-edit-faq.png') });
    await axe(page, 'screen-edit-open');

    // The hero: a word corrected by hand, saved without any generation.
    const hero = page.locator('[data-testid=cms-section-edit-hero]');
    await hero.locator('summary').click();
    const title = hero.locator('input[name="cms_section_hero[title]"]');
    const before = await title.inputValue();
    const after = before.replace(/Lyon/, 'Lyon 3e');
    await title.fill(after);
    await Promise.all([page.waitForNavigation(), hero.locator('[data-testid=cms-section-save]').click()]);
    console.log('saved: ' + (await page.textContent('.cms-toast')).trim().replace(/\s+/g, ' '));
    console.log('hero now reads: ' + (await page.textContent('li[data-section="hero"] .cms-content__item-excerpt')).trim());
    const history = await page.$$eval('[data-testid=cms-content-history] tbody tr', rows => rows.slice(0, 2).map(r => r.innerText.replace(/\s+/g, ' ').trim()));
    console.log('history: ' + JSON.stringify(history));
    await page.screenshot({ path: path.join(out, '07-edited.png') });

    await Promise.all([page.waitForNavigation(), page.click('[data-testid=cms-content-publish] button')]);
    console.log('published: ' + (await page.textContent('.cms-toast')).trim().replace(/\s+/g, ' '));
    const front = await page.getAttribute('[data-testid=cms-page-preview-btn]', 'href');
    await page.goto(front);
    const h1 = await page.$$eval('h1', hs => hs.map(h => h.textContent.trim()));
    console.log('front h1: ' + JSON.stringify(h1));
    await page.screenshot({ path: path.join(out, '08-front-edited.png') });

    fs.writeFileSync(path.join(out, 'browser-log-edit.txt'), log.join('\n'));
    console.log('browser errors: ' + log.length);
    await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
