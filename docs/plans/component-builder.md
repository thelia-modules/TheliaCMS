# Plan: components made in the back office

Status: planned, not started. The design questions are settled (see
"Decisions taken").

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

## Decisions taken

- A page uses a component made in the back office; it never makes one. No
  entry on the content screen, no tool of the page generator.
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
