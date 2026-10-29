# Plan: components made in the back office

Status: done. Every step is in the code, step 10 (tests) with each of the
others. What is left is listed under "Not done". The design questions are
settled (see "Decisions taken").

## What is asked

An editor makes a new component from a prompt in the back office, sees it
rendered before keeping it, and once kept it is a component like any other:
in the catalogue, on **CMS > Components** (switched on or off, with the count
of pages using it), on the toolkit page, offered to the generator, added and
edited by hand on the content screen, exported and imported with the site.

A page uses such a component; a page never makes one. The content screen and
the page generator have no way to create or change a component.

## What is there today

- The catalogue (`Content/SectionCatalog.php`) is built from the
  `SectionDefinitionInterface` services and from the providers
  (`SectionProviderInterface`): the partials (`Content/Partial/PartialSections.php`)
  and the components of the theme (`Content/Theme/ThemeSections.php`). A
  provider backed by a table is the natural way in: nothing else in the
  module needs to know where a component comes from.
- A definition gives `name()`, `label()`, `description()`, `schema()`
  (`Content/Prop.php`, `Content/PropSchema.php`), `sample()`,
  `normalizeProps()` and `render()` (`docs/creating-a-component.md`).
- Everything that reads the catalogue already works from these methods: the
  generator sheet (`Generation/CatalogSheet.php`), the section form
  (`Generation/Admin/SectionFormBuilder.php`), the validator
  (`Content/DocumentValidator.php`), the toolkit page
  (`templates/front/sections/toolkit/all.html.twig`, `Content/Section/SectionStories.php`),
  the components screen (`Content/Admin/CmsComponentAdminController.php`,
  its `origin()` today: theme, partial, module) and the count of pages
  (`Content/Admin/SectionUsageFinder.php`).
- The generator (`Generation/PageGenerator.php`, `Generation/StreamedMessage.php`,
  the ledger `cms_page_generation` with a `target_type`) writes documents of
  pages only.
- What goes online is filtered: `Content/Pipeline/PublishedContentSanitizer.php`
  (`html()` for markup, `css()` for a stylesheet without remote address).
  The site already serves a stylesheet of its own
  (`Front/SiteStylesController.php`, `/cms/site-styles.css`) linked on the
  front and in the preview of the content screen.
- Tailwind utilities written outside the theme are not compiled (project
  pitfall): a component of the module styles itself with its own CSS.
- `/admin/cms/components/*` is guarded by the settings resource
  (`Security/CmsAdminGuard.php`).

## The mechanism

A component made in the back office is a row: a code, a label and a
description, a prop schema written as data, a sample, a template and a
stylesheet. The model writes all of it from the prompt; the site checks it,
renders the sample, and keeps it only when the editor says so.

- **Name**: `Site:<Code>` (`Site:PriceTable`), so it never clashes with the
  theme (`Molecules:…`), the module (`cms:…`) or a partial (`Partial:…`).
  The code is fixed once the component exists: documents refer to it.
- **Props**: the JSON form of the `Prop` constructors (text, html, integer,
  boolean, choice, image, url, items, reference), read into a `PropSchema`.
  Same checks as every other component; nothing new to validate.
- **Template**: Twig, compiled in a dedicated environment with the sandbox
  (`Twig\Extension\SandboxExtension`, sandboxed globally, a strict
  `SecurityPolicy`: `if`, `for`, `set`; the escaping filters, `default`,
  `length`, `join`, `upper`, `lower`; no function, no method, no property of
  an object, no `include`). It receives `props` and nothing else. Every text
  shown comes from a prop: a template holds no wording, so a component works
  in every language of the site.
- **Pictures and links**: an `image` prop goes through `Content/ImageSource.php`
  before the template, like `cms:image`; the publication then rewrites it
  (`ImageRewriter`) as for any image.
- **Styles**: scoped under one class of the component (`.cms-site-<code>`),
  checked by `PublishedContentSanitizer::css()`, and served with the site
  stylesheet (or one of its own with a hash in the address), so the front,
  the preview of a page and the toolkit read the same file. The prompt gives
  the custom properties of the theme (colours, spacing) to use instead of
  fixed values.
- **Rendering**: the markup the template produces goes through the same
  filter as every section when the page is published; nothing a component
  writes reaches a visitor unfiltered.

## Steps

1. **Storage.** Table `cms_component` (code, label, description, props as
   JSON, sample as JSON, template, css, status `draft` or `ready`, version,
   created/updated by and at) in `Config/schema.xml`, `Config/TheliaMain.sql`,
   `Config/update/2.0.0.sql` (the module is not released yet). Model classes
   created in `Model/` before the Propel rebuild (project pitfall).
2. **The definition.** `Content/Site/SiteComponentSection` (a
   `SectionDefinitionInterface` over a row) and `SiteComponentSections`
   (a `SectionProviderInterface` of the `ready` rows). Prop schema read from
   JSON; template rendered by `Content/Site/SiteTemplateRenderer` (the
   sandboxed environment). A row whose template no longer compiles renders
   nothing and is logged; the page still renders.
3. **Checking a component.** `SiteComponentValidator`: the code, the props
   read into a schema, the sample normalised by it, the template compiled
   under the policy, the sample rendered, the stylesheet filtered and scoped.
   Every problem listed at once, as `DocumentValidator` does.
4. **The generator of components.** `Generation/ComponentGenerator`, beside
   `PageGenerator` and sharing its client, streaming, pricing and ledger
   (`target_type` = `component`). Structured output: label, description,
   props, sample, template, css. The system prompt: the prop types and their
   JSON form, the sandbox rules, the custom properties of the theme, the
   accessibility and page-structure skills, two or three components of the
   catalogue as examples. A refused component goes back to the model with
   its problems, twice at most, as a document does. A change asked on an
   existing component sends the component with the prompt.
5. **The screen.** Under **CMS > Components**, behind the settings and the
   custom code resources (`Security/CmsAdminGuard.php` guards the path by
   settings; the custom code check is added in the controller): a "New component" button, a
   screen with the prompt, the outcome (warning, figures of the call), a
   preview of the sample in the theme (an iframe on a signed address, as
   `Preview/CmsPagePreviewController` does for a draft) at three widths, the
   props and the code read only, "Ask a change" and "Keep it". Keeping it
   moves it to `ready`. The list shows the origin "site", and the actions to
   change, disable or delete it.
6. **Changing a component in use.** A new version is checked against the
   stored document of every page using it before it is kept (a prop removed
   or retyped breaks them): the screen lists the pages and what breaks, and
   refuses. The drafts using it are rendered again on keep
   (`thelia_cms:render` code path); the published pages keep their HTML until
   they are published again, and the screen says so.
7. **Deleting.** Refused while a page uses it (`SectionUsageFinder`).
8. **Export and import.** The components go in the export of the site
   before the pages (`ImportExport/SiteExporter.php`, `SiteImporter.php`),
   checked again on import; a page naming a component the file lacks is
   reported as today.
9. **Docs and changelog.** `docs/sections.md` (a sixth family),
   `docs/creating-a-component.md` (a third way), `docs/generator.md`,
   `CHANGELOG.md`.
10. **Tests.** Unit: the prop schema read from JSON, the sandbox policy
    (a function, a method call, an include refused), the scoping of the CSS.
    Integration: a component generated through the fake transport, kept,
    found in `SectionCatalog::offered()`, written by the page generator into
    a page, edited by hand, disabled, exported and imported; a change that
    breaks a page refused; the content screen offers no creation.
11. **Browser.** A real generation of a component at 1440 px and at phone
    width, kept, then used by a page generated from a brief.

## Done

- **Step 1.** `cms_component` in `Config/schema.xml`, `Config/TheliaMain.sql`
  and `Config/update/2.0.0.sql`; `Model/CmsComponent*.php`.
- **Step 2.** `Content/Site/`: `SiteComponent` (the component as written, CSS
  unscoped), `SitePropReader` (JSON form of the props), `SiteTemplateRenderer`
  (the sandboxed Twig), `SiteCssScoper`, `SiteComponentSection` (drawn inside
  `<div class="cms-site cms-site--<code>">`), `SiteComponentSections` (the
  provider of the `ready` rows), `SiteComponentRepository`.
- **Step 3.** `SiteComponentValidator`. Tests: `Tests/Unit/Content/Site/`,
  `Tests/Integration/Content/Site/SiteComponentTest.php`.

- **Step 4.** `Generation/Component/`: `ComponentPrompt` (system prompt
  cached: rules of the props, of the sandbox and of the stylesheet, the
  colour tokens read from `assets/blocks.css`, the accessibility skill, an
  example; user message: the shop, the components the catalogue already
  has, the request, the component to change), `ComponentGenerator`,
  `ComponentRequest`, `ComponentResult`; `GenerationLog::recordComponent()`
  and `GenerationTarget::TYPE_COMPONENT`. Tests:
  `Tests/Integration/Generation/Component/ComponentGeneratorTest.php`.

- **Step 5.** `Content/Admin/CmsSiteComponentController.php` under
  `/admin/cms/components/site` (new, generate, candidates/{id}, keep, {code}),
  `templates/backOffice/default-twig/components/site/`, the list
  (`components/list.html.twig`: "New component", origin "Made on this site",
  "Open"). `Generation/Component/ComponentCandidates.php` reads a candidate
  back from the ledger. `Preview/ComponentPreviewLink.php` and
  `Preview/ComponentPreviewController.php` draw a candidate or a component
  with its sample on a page of the theme. `Content/Site/SiteComponentStyles.php`
  and `/cms/site-components.css`, linked by `Settings/SiteStylesThemeHook.php`
  after the blocks and by the toolkit page. Tests:
  `Tests/Integration/Content/SiteComponentScreenTest.php`.
  Checked at 1440 px with a real call (a price table: one turn, 40 s,
  0.11 USD): candidate drawn in Flexy with its accent, stacked at 375 px
  without horizontal scroll, kept, listed, its stylesheet linked on the front.

- **Steps 6 and 7.** `Content/Site/SiteComponentUsage.php` reads the
  documents (draft and published, every language, pages out of the bin) that
  hold a component and names every section a new version would refuse. The
  generator sends such a version back for correction with the pages named;
  keeping it is refused all the same, on what the pages hold then. Keeping a
  version draws the drafts using it again (`CmsPageWriter::renderDraftAgain`,
  the path of `thelia_cms:render`); a draft that cannot be drawn is named.
  Deleting is refused while a page holds the component; a deleted component
  leaves no name in the list of the ones switched off.

- **Step 8.** `ImportExport/SiteExporter.php` writes the `ready`
  components in a `components` section before the pages
  (`ExportFormat::SECTIONS`); `SiteImporter` imports them before the pages,
  each one through `SiteComponentValidator`; one the site has under the same
  code is kept unless `--replace`, and the report says when the two differ.
  A page of the file naming a `Site:` component that neither the file nor
  the site has is reported: it keeps the HTML of the file, without the
  styles of that component, and cannot be saved until it is made. Tests:
  `Tests/Integration/ImportExport/SiteComponentImportTest.php`.

- **Step 11.** A change asked of a page with `thelia_cms:generate --revise`
  ("our three subscription plans, put Gourmand forward") was written with
  `Site:PricingPlans`, picked by the generator of pages from the catalogue;
  the buttons left out were named in `left_out` (no subscription page on the
  site). In the browser at 1440 px: the draft drawn with the stylesheet of
  the components, the section edited by hand in its dialog (a price changed,
  saved to the document and the HTML). The toolkit page draws it too.

## After the plan

- **Progress.** The request is posted by `assets/component-generator.js`
  and followed as server-sent events (`GenerationStream`, its messages
  speaking of a component), then the screen of the candidate; without the
  script the form waits for the answer as before.
- **Adversarial review** by an agent that had not seen the work. Confirmed
  by probes, then fixed with a test seen failing first: markup props and
  what a template assembles reached the draft and the preview unfiltered
  (the output of a component now goes through
  `PublishedContentSanitizer::html()`, and the preview frame is sandboxed);
  `{% use %}` threw out of the check and `{% extends %}` passed it (refused
  by name, every Twig error caught); `range` and `1..n` had no bound
  (removed); `:not(:scope)`, `& ~ p` reached past the component, and a comma
  in `[title="a,b"]` split a selector (refused, split aware of brackets and
  quotes); `url()` written with an escape and `image-set()` passed the
  filter of the stylesheet (refused, with any backslash). Not acted on:
  `position: fixed` is allowed (whoever makes a component holds the custom
  code permission); `attribute()`, `_self`, `_context` print, giving back
  strings only.

## Not done

- The stylesheet served is the one of the version kept, at once, including
  under published pages still holding the markup of the version before.

What the code settled that the plan did not say:

- The prop types are text, html, integer, boolean, choice, image, url and
  items; an item holds no items. A reference or a JSON value would need a
  function to be of any use in a template: left out.
- **The Twig sandbox checks a template when it is displayed, not when it is
  compiled**: `SiteTemplateRenderer::problems()` runs
  `unwrap($twig)->checkSecurity()` itself, and `render()` refuses a template with
  problems. The `constant` test escapes the policy: refused by a pattern.
- Functions of values are allowed (`range`, `cycle`, `min`, `max`): `1..3`
  compiles to `range`.
- The template may not hold `script`, `style`, `iframe`, `object`, `embed`,
  `form`, `base`, `meta`, `link`, an `on…=` attribute or `javascript:`: the
  publication filters a page, but a draft is drawn in the back office first.
- The template writes no text of its own: text outside its tags and prints
  is refused.
- The stylesheet is checked as written (the model is told about an
  `@import`) and kept filtered by `PublishedContentSanitizer::css()`; it is
  scoped when served. At-rules other than `@media`, `@supports`,
  `@container` are refused, `@keyframes` included.
- The generator of components offers no tool: one request, the component,
  and up to two corrections. The answer carries the props and the sample as
  JSON strings, like the props of a document.
- A new component has no row until it is kept: its calls are in the ledger
  under `target_type` `component` and `target_id` 0, and count in the spend.
  A change is recorded under the id of the component.
- A new component whose code is taken, or a change that renames its
  component, is sent back for correction like any other problem.
- A candidate is a row of the ledger (its `document` holds the component):
  nothing else stores it until it is kept. A change asked on a candidate
  starts from it; keeping a new one files its call under the new row
  (`ComponentCandidates::attach()`). The `draft` status of `cms_component`
  is the prototype made from a page ([component-prototype.md](component-prototype.md)).
- The call is a plain POST that waits for the answer (up to a minute), the
  button saying so: the progress stream of the content screen lives in its
  own script, not reused here.
- The preview is a page of the front office on an address signed for an hour,
  its key salted apart from the links of draft pages; the stylesheet of the
  candidate is printed in it, scoped. A stylesheet holding `<` is refused by
  the validator, and escaped in the preview all the same.
- `SectionCatalog` implements `ResetInterface`: a component kept in one
  request is in the catalogue of the next, in a worker serving many.
- The stylesheet served is the one of the version kept, at once: a published
  page keeps the markup of the version it was published with until it is
  published again, under the rules of the new one. A change that renames a
  class shows on such a page before the page is published again.
- The routes of a component take `{component}`, not `{code}`: the other
  `{code}` of the module are lowercase, and `WriteRoutesTest` builds one
  sample per parameter name.
- `.cms-site` (the element a component is drawn in) has the width of the
  content in `assets/blocks.css`, like the other blocks.

## Decisions taken

- A page uses a component made in the back office; it never makes one. No
  entry on the content screen, no tool of the page generator. *Reversed by
  [component-prototype.md](component-prototype.md): a page prototypes one,
  and a brief may make one when a setting allows it; the prototype is
  offered to nobody else until it is kept for the whole site.*
- A component made in the back office goes through the catalogue like the
  others: no screen or generator reads its table directly.
- A template holds no wording: every text is a prop.
- The model writes a sandboxed Twig template and a scoped stylesheet, not an
  assembly of components of the theme: an assembly makes nothing the theme
  cannot already draw.
- Making or changing a component takes the settings resource and the custom
  code resource: it writes markup and styles shown on every page using it.
- No editing of the code by hand in this phase: the template and the
  stylesheet are shown read only and changed by prompt, so every version
  goes through the generator and the validator.
- One current version per component. A change that breaks a page using it is
  refused with the pages listed; drafts are rendered again, published pages
  at their next publication.

## Resuming

Read, in order: this file, `docs/creating-a-component.md`, `docs/sections.md`,
`Content/SectionCatalog.php`, `Content/Theme/ThemeSections.php` (a provider),
`Generation/PageGenerator.php`, `Generation/CatalogSheet.php`,
`Content/Admin/CmsComponentAdminController.php`,
`Content/Pipeline/PublishedContentSanitizer.php`. Follow the steps in order,
each with its tests.
