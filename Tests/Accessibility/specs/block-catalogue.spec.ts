import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

/**
 * Accessibility of the pages a visitor reads.
 *
 * The block catalogue is the page that holds every block of the catalogue at
 * once, which makes it the one page where a regression in any block shows up.
 * The markup comes from the module and the styling from the theme, so a
 * violation found here belongs to one or the other, never to a project.
 *
 * The bar is zero critical and zero serious. Moderate and minor findings are
 * reported rather than failed: some of them are decisions (the contrast of a
 * brand colour), and hiding them behind a green run would be worse than seeing
 * them scroll past.
 */

const PAGES = [
  { name: 'block catalogue', path: process.env.CATALOGUE_PATH ?? '/catalogue-des-blocs' },
  { name: 'search results', path: '/recherche?q=bloc' },
  { name: 'a page that does not exist', path: '/cette-adresse-nexiste-pas' },
];

async function scan(page: Page) {
  return (
    new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      // The Symfony debug toolbar is injected in dev and never reaches a
      // visitor. Its own contrast and link-name failures would drown the ones
      // that belong to the theme.
      .exclude('.sf-toolbar')
      .exclude('.sf-minitoolbar')
      .analyze()
  );
}

for (const { name, path } of PAGES) {
  test(`${name} has no critical or serious accessibility violation`, async ({ page }) => {
    await page.goto(path);

    const { violations, passes, incomplete } = await scan(page);
    const blocking = violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    const rest = violations.filter((v) => !blocking.includes(v));

    // Printed on every run, passing or not: a scan that silently checks nothing
    // reads exactly like a page with nothing wrong with it.
    console.log(
      `${name}: ${passes.length} rule(s) passed, ${violations.length} violation(s), ` +
        `${incomplete.length} needing a human look` +
        rest.map((v) => `\n  [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node(s))`).join('') +
        incomplete
          .map((v) => `\n  [incomplete] ${v.id}: ${v.help}\n${v.nodes.map((n) => `    ${n.target.join(' ')}`).join('\n')}`)
          .join(''),
    );

    expect(
      blocking.map((v) => `[${v.impact}] ${v.id}: ${v.help}\n${v.nodes.map((n) => n.html).join('\n')}`),
    ).toEqual([]);
  });
}

test('the block catalogue starts with a skip link that reaches the content', async ({ page }) => {
  await page.goto(PAGES[0].path);
  await page.keyboard.press('Tab');

  const focused = page.locator(':focus');
  await expect(focused).toBeVisible();

  const target = await focused.getAttribute('href');
  expect(target, 'the first thing reached by the keyboard is not a skip link').toMatch(/^#/);
  await expect(page.locator(target!)).toHaveCount(1);
});

test('the block catalogue has exactly one first-level heading', async ({ page }) => {
  await page.goto(PAGES[0].path);

  await expect(page.locator('h1')).toHaveCount(1);
});
