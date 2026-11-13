# Plan: a component of the site draws another (N71)

Status: designed on 2026-11-13, not started. Decision in ADR 15. The cases
`compose-*` of the bench of components measure it; a round needs the
agreement of the maintainer (paid).

## What is asked

An editor asks the generator for "two columns: a text on the left, a carousel
of photos on the right". The site already has a `Carousel`. Today the
generator writes a new component that draws a carousel again, with its own
markup and stylesheet. It should place the `Carousel` of the site
instead, so that a change to the carousel reaches every component that shows
one.

## What is there today

- The sandbox of a component of the site allows the tags `if`, `for`, `set`
  and the functions `cycle`, `min`, `max`: no `include`
  (`Content/Site/SiteTemplateRenderer.php`, `TAGS`, `FUNCTIONS`). The prompt
  says so (`Generation/Component/ComponentPrompt.php`, "# Template").
- A template sees `props` in their template form: an `html` prop is a
  `Twig\Markup`, an image is its address, a video the address of its file, an
  embed the markup of its facade (`SiteComponentSection::forTemplate()`).
- What a component draws is filtered by `PublishedContentSanitizer`, then put
  in `<div class="cms-site cms-site--<code>">`. Every stylesheet of the site is
  scoped to that class and served in one file on every page
  (`SiteComponentStyles`, `SiteStylesThemeHook`): the stylesheet of a
  component is already on the page of any other.
- The bench plays three composition cases, counted apart (`gap: N71`):
  `compose-text-carousel`, `compose-quote-figures`, `compose-steps-faq`
  (`docs/evaluation/components/cases/`). The seed of the bench holds
  `Quote`, `ImageText`, `KeyFigures`, `Steps`, `Carousel` and `Faq`.
- The icons (#153, 2.27.0) added `cms_icon()` to the sandbox and counts the
  components whose template cites an icon: the same shape as this lot.

## Design (ADR 15)

1. **`cms_component('Carousel', {slides: props.photos})`** in the template of
   a component of the site draws the kept component of the site of that code
   with the values given, in their template form (what the parent's `props`
   already are). The function is safe HTML: the child is drawn in the same
   sandbox, wrapped in its own `cms-site cms-site--<code>` element, so its
   stylesheet applies.
2. **The parent declares the props it passes.** The text and the photos of
   the carousel are props of the parent, of the usual types. The editor
   form, the translation, the document checker and the media usage read the
   parent's schema and its rendered markup as today: none of them changes.
3. **Checked when the component is saved** (`SiteComponentValidator`, from
   the Twig tree, not a regex):
   - the code is a constant string naming a kept component of the site (a
     prototype or a theme component is refused);
   - not the component itself;
   - one level: a component that calls `cms_component` cannot be called
     (and a called one cannot start calling);
   - the keys passed are props of the child;
   - at most 24 calls are drawn per rendering of the parent (a call inside
     a loop counts each time).
4. **At render**: an unknown or refused code, or a child that fails, draws
   nothing and is logged, as a failing component does today. The output of
   the parent, child included, goes through the sanitizer once.
5. **A cited component is held.** It cannot be deleted while a component
   cites it. A new version of it that drops or renames a key a parent
   passes is listed among its breakages (`SiteComponentUsage::breakages()`),
   the way the sections of pages are.
6. **The generator is told.** The prompt states the function and its rules.
   The user message gives, for each kept component of the site, its code and
   its props (name, type, the props of an item). The rule "the site already
   has it" stays: a request the catalogue answers whole is `existing`. A
   request that combines what the site has is a new component that calls it.

## Steps

1. **Renderer**: `cms_component()` in `SiteTemplateRenderer`, the child looked
   up by code through a small reader of kept components (no dependency on the
   catalogue, to keep the service graph without a cycle); depth and call
   count enforced at render. Unit tests: drawn, wrapped, escaped, unknown
   code, depth, count. Check that the sanitizer keeps the child's wrapper
   class.
2. **Validator**: the checks of point 3 from the Twig tree; messages in both
   languages. Tests per refusal.
3. **Usage**: deletion refused while cited; breakages of a new version;
   "cited by N components" on the sheet of a component (the drawer of linked
   pages lists pages only: components are named in the sentence).
4. **Generator**: prompt rule, catalogue of site components in the user
   message, recorded answers replayed by `FakeAiGateway`.
5. **Bench**: the `compose-*` contracts gain `"template": {"require":
   ["cms_component("]}` (or the oracle reads the calls) and a markup marker
   of the child (`cms-site--carousel`); `gap` removed. A round of the three
   cases × 3 trials, about 1 USD (0.075 to 0.147 USD per trial in rounds 0
   to 3, and a longer prompt), on the agreement of the maintainer.
6. **Docs**: `docs/creating-a-component.md` (the function, the rules),
   `CHANGELOG.md`, ADR 15 accepted.

## Open points

- **Styles that cross.** A rule of the parent (`.title`) also matches inside
  the child, whose wrapper is under the parent's. The prompt can tell the
  parent to style its own parts only. A scoper that stops at
  `.cms-site` (`@scope (.cms-site--a) to (.cms-site)`) needs `@scope`: to
  check against the browsers the shops support.
- **Theme components as children.** A Flexy component is drawn outside the
  sandbox from its stored props (an image is an id, not an address): not in
  this lot.
- **A child of a child.** One level answers the three cases of the bench; a
  second level would need a cycle check and a cost bound per page.
