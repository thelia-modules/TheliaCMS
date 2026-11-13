# Plan: a component of the site draws a component of the theme

Status: designed on 2026-11-13, not started. Decision in ADR 16 (amends
ADR 15).

## What is asked

"A text next to the related products": the theme has the component that
draws the products (Flexy, `Layouts:CrossSelling:Base` and the like); a
component made in the back office should place it, as it places a
component of the site since 2.27.0.

## What is there today

- `cms_component()` draws a kept component of the site with values in their
  template form (`Content/Site/SiteTemplateRenderer.php`, `draw()`).
- A component of the theme is drawn by `ThemeComponentSection::render()` from
  stored values, through the Twig of the theme, outside the sandbox
  (`Content/Theme/ThemeComponentSection.php`, `draw()`).
- `SiteComponentSection::render()` filters the caller's markup with a
  `PublishedContentSanitizer` built without the controllers of the theme
  (`Content/Site/SiteComponentSections.php` passes none): the Stimulus
  attributes of a theme component would be taken out.
- The nine trials of round 4 pass `props.<name>` only.

## Design (ADR 16)

1. **Stored values.** `SiteComponentCall` keeps, per key, either the name of
   the prop of the caller or the literal written. The renderer receives the
   stored props of the caller along with their template form; a call hands
   the called section the stored values.
2. **One path per kind.** The renderer resolves a name through the catalogue
   of sections (a lookup that keeps the service graph without a cycle: the
   catalogue builds the site sections, which build the renderer): `Site:` +
   code for the site, the theme name otherwise. It calls `normalizeProps()`,
   then `render()` of that section. A component of the site is drawn in the
   sandbox as today; a component of the theme by the theme.
3. **Placeholders.** A theme call prints a placeholder (a random token per
   rendering); `SiteComponentSection::render()` filters the markup, then puts
   the theme markup in place of each token.
4. **Validation.** A value is `props.<name>` or a literal; the name is
   offered by the catalogue, not hidden, not a prototype; the keys are props
   of the called schema; a prop passed as it is reads as the prop it fills
   (`SiteComponentCallFit`).
5. **Generator.** The catalogue lines mark the drawable theme components
   with their name and props, as for the site ones.

## Steps

1. Calls read literals and refuse other expressions; stored values reach the
   called section; site children go through `SiteComponentSection` (tests of
   ADR 15 kept, `item.x` test turned into a refusal).
2. Theme children through the catalogue, placeholders after the filter;
   integration test with the toolkit component of the test theme
   (`Layouts:CrossSelling:Base`, skipped without Flexy as the screen tests
   are).
3. Validation and generator lines for theme names; docs, CHANGELOG, ADR 16
   accepted.
4. Bench: one case "a text next to the related products" with the theme
   component as target; a round of 3 trials (about 0.20 USD) on the
   agreement of the maintainer.

## Open points

- A theme component that needs the context of a page (a product, a
  category) gets none from a component of the site: its props must carry
  the ids it reads.
- The preview of a component runs the theme scripts in a frame without an
  origin of its own: a theme controller that reads the session or calls
  the shop will fail there, as it does in the preview of a page section.
