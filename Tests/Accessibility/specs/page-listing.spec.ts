import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

/**
 * Accessibility and keyboard operation of the page listing in the back office.
 *
 * This is the screen an editor opens every day, and until now nothing scanned it:
 * the suite stopped at the front office because it did not log in. It does now,
 * which also lets it prove the two things a scan cannot: that the tree can be
 * unfolded without a mouse, and that no control on the screen is smaller than the
 * 24 by 24 WCAG 2.2 asks for.
 *
 * Nothing here writes to the site. Reordering, publishing and binning are driven
 * from the back office by real forms and are covered by the PHP suites; a browser
 * test that moved pages around would leave the shop it ran against changed.
 */

const LOGIN = process.env.ADMIN_LOGIN ?? 'thelia';
const PASSWORD = process.env.ADMIN_PASSWORD ?? 'thelia';

/**
 * Asking for every state at once is what puts all five publication badges on one
 * screen. Scanning the plain tree instead would scan whichever states the shop
 * happens to have, and on a fresh install that is one of them.
 */
const EVERY_STATE =
  '/admin/cms/pages?status%5B%5D=draft&status%5B%5D=scheduled&status%5B%5D=published'
  + '&status%5B%5D=modified&status%5B%5D=unpublished';

const VIEWS = [
  { name: 'page tree', path: '/admin/cms/pages' },
  { name: 'page search results', path: '/admin/cms/pages?q=a' },
  { name: 'pages in every state', path: EVERY_STATE },
  { name: 'page bin', path: '/admin/cms/pages/trash' },
];

async function signIn(page: Page) {
  await page.goto('/admin/login');

  // Already signed in: the shop sends the login screen to the dashboard.
  if (!page.url().includes('/admin/login')) {
    return;
  }

  await page.locator('[data-testid="login-username"]').fill(LOGIN);
  await page.locator('[data-testid="login-password"]').fill(PASSWORD);

  // The "remember me" box of the back-office login form carries `required`, so a
  // browser refuses to submit the form until it is ticked. That belongs to the
  // theme, not to this module; ticking it is how a person gets in today.
  await page.locator('#thelia_admin_login_remember_me').check();

  await page.locator('[data-testid="login-submit"]').click();
  await expect(page.locator('[data-testid="login-submit"]')).toHaveCount(0);
}

/**
 * How many different publication badges are on the screen right now.
 */
async function badgeVariety(page: Page): Promise<number> {
  return page.evaluate(
    () =>
      new Set(
        [...document.querySelectorAll('tbody tr[data-row-id] td:nth-child(2) .badge')].map((badge) => badge.className),
      ).size,
  );
}

/**
 * The identifier of a language whose pages are not all in the same state.
 *
 * Publication is per language: a shop can hold six hundred pages written in French
 * and, in English, six hundred drafts. Scanning the state the shop happens to
 * answer in would then measure the colour of one badge out of five and report the
 * other four as fine. Found rather than hardcoded, because no identifier is the
 * same from one install to the next.
 */
async function languageShowingSeveralStates(page: Page): Promise<string> {
  await page.goto(EVERY_STATE);

  const languages = await page.evaluate(() =>
    [...document.querySelectorAll('.bo-page-header a[href*="edit_language_id="]')]
      .map((link) => new URL((link as HTMLAnchorElement).href).searchParams.get('edit_language_id'))
      .filter((id): id is string => null !== id),
  );

  const known = [...new Set(languages)];

  for (const language of known) {
    await page.goto(`${EVERY_STATE}&edit_language_id=${language}`);

    if ((await badgeVariety(page)) > 1) {
      return language;
    }
  }

  // A shop whose pages are all in the same state still gets scanned, on the
  // language it answers in. How much that measured is printed by the caller.
  return known[0] ?? '';
}

async function scan(page: Page) {
  return (
    new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      // Injected in dev and never served to anybody: its own failures would
      // drown the ones that belong to the screen.
      .exclude('.sf-toolbar')
      .exclude('.sf-minitoolbar')
      .analyze()
  );
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
});

/**
 * Colour pairs that fail the contrast rule everywhere in the back office because
 * they are the palette of the theme, not choices this screen makes.
 *
 * They are listed rather than ignored. A screen of the module cannot fix the link
 * colour of the theme it is rendered in, and hardcoding a compliant colour in a
 * module template would only hide the problem on one screen and break the day the
 * theme is fixed. What this module owes is not to add a pair of its own, which is
 * what the assertion below measures.
 *
 * Reported to the dev as a defect of `thelia/default-twig`: the brand orange used
 * for links and for the primary button holds 2.99:1 and 3.21:1 where WCAG AA asks
 * for 4.5:1 on text.
 */
const THEME_PALETTE_PAIRS = [
  '#f26041 on #f4f7fa', // link colour of the back office, over a card
  '#f26041 on #fafbfc', // the same link colour, over the header of a card
  '#ffffff on #f26041', // primary button
  '#f26041 on #313131', // side navigation, current section
  '#fde7e3 on #f26041', // side navigation footer
  '#7b7b7b on #ffffff', // secondary text
  '#78797a on #f4f7fa', // muted text over a card
];

/**
 * @return every failing foreground/background pair of the contrast rule
 */
function contrastPairs(violations: Array<{ id: string; nodes: Array<{ failureSummary?: string }> }>): string[] {
  const pairs = new Set<string>();

  for (const violation of violations.filter((v) => v.id === 'color-contrast')) {
    for (const node of violation.nodes) {
      const found = /foreground color: (#[0-9a-f]{6}), background color: (#[0-9a-f]{6})/i.exec(node.failureSummary ?? '');

      if (null !== found) {
        pairs.add(`${found[1].toLowerCase()} on ${found[2].toLowerCase()}`);
      }
    }
  }

  return [...pairs].sort();
}

for (const { name, path } of VIEWS) {
  test(`${name} has no critical or serious accessibility violation`, async ({ page }) => {
    await page.goto(path);

    const { violations, passes, incomplete } = await scan(page);
    // Contrast is judged apart, against the palette of the theme.
    const own = violations.filter((v) => v.id !== 'color-contrast');
    const blocking = own.filter((v) => v.impact === 'critical' || v.impact === 'serious');
    const rest = own.filter((v) => !blocking.includes(v));

    const pairs = contrastPairs(violations);

    // Printed on every run, passing or not: a scan that silently checks nothing
    // reads exactly like a screen with nothing wrong with it.
    console.log(
      `${name}: ${passes.length} rule(s) passed, ${own.length} violation(s) of its own, ` +
        `${incomplete.length} needing a human look, ` +
        `${pairs.length} colour pair(s) below 4.5:1` +
        pairs.map((pair) => `\n  [contrast] ${pair}${THEME_PALETTE_PAIRS.includes(pair) ? ' (theme palette)' : ' (NEW)'}`).join('') +
        rest.map((v) => `\n  [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node(s))`).join('') +
        incomplete
          .map((v) => `\n  [incomplete] ${v.id}: ${v.help}\n${v.nodes.map((n) => `    ${n.target.join(' ')}`).join('\n')}`)
          .join(''),
    );

    expect(passes.length, 'the scan exercised no rule, so it measured nothing').toBeGreaterThan(0);
    expect(
      blocking.map((v) => `[${v.impact}] ${v.id}: ${v.help}\n${v.nodes.map((n) => n.html).join('\n')}`),
    ).toEqual([]);
    expect(
      pairs.filter((pair) => !THEME_PALETTE_PAIRS.includes(pair)),
      'this screen introduced a colour pair below 4.5:1 that the theme palette does not explain',
    ).toEqual([]);
  });
}

test('every publication badge holds its contrast', async ({ page }) => {
  const language = await languageShowingSeveralStates(page);
  const seen: string[] = [];
  const missing: string[] = [];

  // One state at a time. Asking for all five together only shows the badges of the
  // fifty pages that come first by title, which on a real site is one or two of
  // them: the colour of the other three would be reported as fine unmeasured.
  for (const state of ['draft', 'scheduled', 'published', 'modified', 'unpublished']) {
    await page.goto(`/admin/cms/pages?edit_language_id=${language}&status%5B%5D=${state}`);

    if (0 === (await page.locator('tbody tr[data-row-id]').count())) {
      missing.push(state);
      continue;
    }

    const { violations, passes } = await scan(page);
    const pairs = contrastPairs(violations);
    const introduced = pairs.filter((pair) => !THEME_PALETTE_PAIRS.includes(pair));

    seen.push(`${state}: ${passes.length} rule(s) passed, ${pairs.length} pair(s) below 4.5:1`
      + introduced.map((pair) => ` NEW ${pair}`).join(''));

    expect(introduced, `the ${state} badge is below 4.5:1`).toEqual([]);
  }

  console.log(
    `publication badges (language ${language}):\n  ${seen.join('\n  ')}`
      + (missing.length > 0 ? `\n  NOT MEASURED, no page is in these states: ${missing.join(', ')}` : ''),
  );

  // A shop with no page in a given state says nothing about that badge, which is
  // printed above rather than passed over silently. What is refused is a run that
  // measured almost nothing: on a fresh install the seeded legal pages are drafts,
  // so one state is normal and five is what a real site reaches.
  expect(seen.length, 'fewer than one badge was measured, so this test proved nothing').toBeGreaterThan(0);
});

test('every control of the listing is at least 24 by 24', async ({ page }) => {
  await page.goto('/admin/cms/pages');

  const undersized = await page.evaluate(() => {
    // The buttons, the fold controls of the tree and the controls of the filter
    // bar. Links whose target is a word inside a line of text are left out on
    // purpose: WCAG 2.2 exempts them, and a page title in a table cell is one.
    const controls = [
      ...document.querySelectorAll(
        '.cms-tree__fold, table .btn, .bo-page-header .btn, ' +
          '[data-testid="cms-pages-filters"] .btn, [data-testid="cms-pages-filters"] select, ' +
          '[data-testid="cms-pages-filters"] input[type="search"], ' +
          '[data-testid="cms-pages-active-filters"] a, [data-testid="cms-trash-filters"] .btn',
      ),
    ];

    const measured = controls
      .map((control) => {
        const box = control.getBoundingClientRect();
        return { html: control.outerHTML.slice(0, 90), width: Math.round(box.width), height: Math.round(box.height) };
      })
      // A control the browser gives no box to is not on screen; only what is
      // shown can be aimed at.
      .filter((seen) => seen.width > 0 && seen.height > 0);

    return { counted: measured.length, tooSmall: measured.filter((seen) => seen.width < 24 || seen.height < 24) };
  });

  // Without this, a selector that stopped matching anything would report a
  // screen where every target is big enough.
  expect(undersized.counted, 'no control was measured, so nothing was checked').toBeGreaterThan(10);
  expect(undersized.tooSmall).toEqual([]);
});

test('a branch of the tree opens with the keyboard alone', async ({ page }) => {
  await page.goto('/admin/cms/pages');

  const fold = page.locator('[data-testid^="cms-page-fold-"]').first();
  await expect(fold, 'the tree has no branch to open, so this proves nothing').toHaveCount(1);

  // A site small enough opens whole by itself, so the first control on screen may
  // be a "close" rather than an "open". Either way it has to answer the keyboard,
  // and the state has to flip.
  const wasOpen = 'true' === (await fold.getAttribute('aria-expanded'));
  const rowsBefore = await page.locator('tr[data-row-id]').count();

  // Reached and pressed as a keyboard user does, not clicked: the fold control
  // is the one thing on this screen that a pointer could otherwise be the only
  // way to use.
  await fold.focus();
  await expect(page.locator(':focus')).toHaveAttribute('data-testid', await fold.getAttribute('data-testid') ?? '');
  await page.keyboard.press('Enter');

  // The number of rows changed, so the branch really opened or really closed.
  await expect(page.locator('tr[data-row-id]')).not.toHaveCount(rowsBefore);

  const nowOpen = 'true' === (await page.locator(`[data-testid="${await fold.getAttribute('data-testid')}"]`).getAttribute('aria-expanded'));
  expect(nowOpen, 'the control did not change what it says about the branch').toBe(!wasOpen);

  // What is open is in the address, which is what makes a bookmark and the back
  // button of the browser land on the screen the editor left.
  expect(page.url()).toContain('open');
});

test('searching the listing needs nothing but the keyboard', async ({ page }) => {
  await page.goto('/admin/cms/pages');

  const search = page.locator('[data-testid="cms-pages-search-input"]');
  await search.focus();
  await page.keyboard.type('a');
  await page.keyboard.press('Enter');

  await expect(page.locator('[data-testid="cms-pages-result-count"]')).toBeVisible();
  expect(page.url()).toContain('q=a');
});

test('the listing reports no error of its own', async ({ page }) => {
  for (const { path } of VIEWS) {
    await page.goto(path);

    const shown = await page.evaluate(() =>
      [...document.querySelectorAll('.alert-danger, .invalid-feedback, .is-invalid')].map((node) =>
        (node.textContent ?? '').trim(),
      ),
    );

    expect(shown, `errors displayed on ${path}`).toEqual([]);
  }
});
