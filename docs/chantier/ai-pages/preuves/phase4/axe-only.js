const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const base = 'https://thelia-cms-ai.ddev.site';
const axeSource = fs.readFileSync(path.join(__dirname, 'node_modules/axe-core/axe.min.js'), 'utf8');

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
    const page = await context.newPage();
    await page.goto(base + '/admin/login');
    await page.fill('[data-testid=login-username]', process.env.PROOF_LOGIN);
    await page.fill('[data-testid=login-password]', process.env.PROOF_PASSWORD);
    await Promise.all([page.waitForURL(/\/admin(?!\/login)/), page.click('[data-testid=login-submit]')]);

    for (const url of process.argv.slice(2)) {
        await page.goto(base + url);
        await page.waitForTimeout(1500);
        await page.screenshot({ path: process.env.SHOTS + '/' + url.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') + '.png' });
        await page.addScriptTag({ content: axeSource });
        const results = await page.evaluate(async () => await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa'] }));
        const violations = results.violations.filter(v => !v.nodes.every(n => n.target.join(' ').includes('sf-toolbar')));
        console.log(url + ': ' + violations.length + ' violation(s) outside the debug toolbar');
        for (const v of violations) {
            console.log('  ' + v.id + ' (' + v.impact + '): ' + v.nodes.map(n => n.target.join(' ')).join(' | '));
        }
    }

    await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
