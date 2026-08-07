# Accessibility checks

`@axe-core/playwright` over the pages a visitor reads, with one rule: no
critical and no serious violation. Moderate and minor findings, and the ones
axe cannot decide on its own, are printed on every run rather than failed.

The block catalogue is the page worth scanning, because it holds every block of
the catalogue at once: a regression in any of them shows up here. The search
results and a missing address are scanned too, since both are pages the module
renders and neither is made of blocks.

## Running them

The browser is driven from outside the container, against a running shop:

```bash
cd Tests/Accessibility
npm install
npx playwright install chromium     # once
npx playwright test
```

`BASE_URL` points somewhere else than `https://thelia-cms.ddev.site`, and
`CATALOGUE_PATH` names the demo page if it lives at another address.

The shop needs the block catalogue published, with all ten blocks and the three
click-to-load embeds on it. Without that page the scan still passes, and proves
nothing.

## What the last run says

Zero violations on the three pages, 19 to 24 rules exercised on each.

One finding is left for a human, on all three: `aria-valid-attr-value` on the
language switcher. Its `aria-controls` names the list of languages, which is in
the page but hidden until the button is pressed, and axe declines to decide
whether a hidden target counts. The list is there, and the id matches.

Two real failures were found the first time this ran, both in the shell of the
theme rather than in the blocks, and both fixed in `thelia/flexy-cms`:

- the logo linked to the home page with a picture and no text, so the first link
  of every page was announced as "link";
- the copyright line was grey on the footer background, 4.2:1 where small text
  has to hold 4.5:1.

They come from the Flexy design system the theme derives from, so they are
likely to exist in any project built on it.

## What this does not cover

The back office. GrapesJS is a drag-and-drop editor and is not operable by
keyboard, which is a documented limit of the module rather than something a
scan would tell us. Contrast inside a page is the responsibility of whoever
writes it: the editor shows the ratio of a colour as it is picked, and the
palette it offers was checked, but nothing stops an author from typing a
hex value.
