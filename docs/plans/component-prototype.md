# Plan: a component prototyped from a page

Status: done. Every step is in the code with its tests. What is left is
listed under "Not done"; the design questions are settled under "Decisions
taken". It follows [component-builder.md](component-builder.md), whose first
decision ("a page never makes a component") it reverses.

## What is asked

An editor writing a page meets a brief the catalogue cannot show: a timeline,
a table of plans. They should prototype the component from the content screen,
without leaving the page, and a brief should be able to make one on the fly.
The section dialogs of the content screen should open as drawers over the left
panel rather than as centred modals.

## What is there today

- `Generation/Component/ComponentGenerator.php` makes a component from a
  request; `ComponentCandidates` keeps the candidate in the ledger;
  `ComponentPreviewLink` signs its preview; `CmsSiteComponentController`
  drives all that from **CMS > Components**.
- `cms_component` has a `status` column, `draft` or `ready`; only `ready`
  was read (`SiteComponentRepository::ready()`).
- `PageGenerator` offers the tools implementing `GeneratorToolInterface`,
  and the schema of its answer (`DocumentSchema::strict()`) is an enum of
  the component names known when the call starts.

## The mechanism

- A prototype is a component of the site with status `draft`. The catalogue
  holds it (`SectionCatalog::all()`, through `SiteComponentSections`, which
  reads every row and marks `SiteComponentSection::$prototype`); `offered()`
  leaves it out, before the availability list. The pages holding it render,
  validate, edit and export it; nobody else is offered it.
- **CMS > Components** lists a prototype with a badge and a **Keep for the
  whole site** form (`admin.cms.components.site.promote`,
  `SiteComponentRepository::promote()` sets `ready`).
- On the content screen, **Add a section > A new component** posts to
  `CmsPageComponentController::generate()`, which streams the nested call
  through `GenerationStream` and comes back to the screen with
  `?component=<ledger id>`. `ContentScreen` then shows the candidate: its
  preview in place of the draft (the iframe sandboxed, the frame handlers of
  the script skipped), its props, the forms to change, discard or keep.
  `keep()` validates, saves as `draft`, attaches the ledger row, resets the
  catalogue, appends a section with the sample props, writes a `manual` row.
- `Generation/Tool/MakeComponentTool.php` is the tool of the brief: not a
  `GeneratorToolInterface`, so it is never offered by the tag; `PageGenerator`
  adds its definition when `PromptBuilder::mayMakeComponents()` says so
  (request flag from the editor's rights, setting
  `generator_component_making`, not a translation). After a turn that made a
  component, the output schema is built again with the new names.
- Rights: `Content/Site/SiteComponentPermission::mayMake()` (settings update
  and custom code update), the same as **CMS > Components > New component**.
- The dialogs of the content screen (`.cms-content__edit`, `.cms-content__add`)
  are fixed drawers on the left, `clamp(26rem, 40vw, 50rem)` wide, sliding in
  with `@starting-style`, full width under 992 px; the backdrop is light so
  the preview stays in sight.

## Done

- `Content/Site/SiteComponentRepository.php`: `all()`, `prototypes()`,
  `promote()`. `SiteComponentSections` and `SiteComponentStyles` read `all()`.
- `Content/SectionCatalog.php`: `offered()` leaves prototypes out
  (`Tests/Unit/Content/SectionCatalogPrototypeTest.php`).
- `Content/Site/SiteComponentPermission.php`, `Content/Site/SitePropRows.php`
  (shared by the two screens).
- `Content/Admin/CmsComponentAdminController.php`,
  `Content/Admin/CmsSiteComponentController.php`: badge, promote route, texts.
- `Generation/Admin/CmsPageComponentController.php`,
  `Generation/Admin/ContentScreen.php`, `content/_prototype.html.twig`,
  `content/edit.html.twig` (`Tests/Integration/Generation/ComponentPrototypeScreenTest.php`).
- `Generation/Tool/MakeComponentTool.php`, `Generation/PageGenerator.php`,
  `Generation/PromptBuilder.php`, `Generation/GenerationRequest.php`,
  `Generation/Admin/GenerationStream.php`, `Generation/Tool/ReadSectionTool.php`
  (a prototype has a sheet) (`Tests/Integration/Generation/MakeComponentToolTest.php`).
- `Generation/GeneratorSettings.php`, `Settings/Admin/*`, `settings/edit.html.twig`
  (`Tests/Integration/Settings/GeneratorSettingsScreenTest.php`).
- `ImportExport/SiteExporter.php`, `SiteImporter.php`: `status` travels
  (`Tests/Integration/ImportExport/SiteComponentImportTest.php`).
- `assets/content-editor.css`, `assets/content-editor.js`: drawers, prototype
  panel, keep form posted in the background.
- Checked in the browser at 1440 px and 1920 px: the two drawers rest over
  the panel, the preview in sight, Esc closes them, the prototype form is in
  the add drawer.

## Not done

- No command of the console prototypes a component: the brief and the screen
  do. `thelia_cms:component` or the like would be a step of its own.
- The catalogue items screen (`Catalogue/Admin/ItemContentController.php`)
  has no prototype entry; its subject has no `component_generate` route, so
  the add drawer shows no "A new component" family there.
- The cost of the nested call is a row of its own in the ledger, under
  `target_type` `component`; it is not summed into the row of the page.
- A prototype is not removed when the last page holding it drops it; it stays
  listed under **CMS > Components** until kept or deleted there.
- The add drawer shows a family heading ("A new component") and a form label
  ("Prototype a component") one above the other.

## Decisions taken

- A prototype is the `draft` status of `cms_component`, not a table or a flag
  of its own: the row, the stylesheet and the export already exist for it.
- A prototype is offered to nobody else until an admin keeps it for the whole
  site: a page experiments, the site decides.
- Same rights as **CMS > Components**, plus a generator setting off by
  default for the brief: making a component writes markup and styles, and a
  brief that makes one costs a nested call.
- The tool is not a `GeneratorToolInterface`: it is offered on the setting
  and the rights, never by the tag.
- Every dialog of the content screen is a drawer, not only the section edit:
  one behaviour for the screen.
- Hors-ticket: branch `feat/component-prototype` from `feat/ai-page-generation`.

## Resuming

Read, in order: this file, [component-builder.md](component-builder.md),
`docs/creating-a-component.md` (§ "A component made in the back office"),
`docs/generator.md` (tools table, § "The screen", § "Components made in the
back office"), `Content/SectionCatalog.php`,
`Content/Site/SiteComponentRepository.php`,
`Generation/Admin/CmsPageComponentController.php`,
`Generation/Tool/MakeComponentTool.php`, `Generation/PageGenerator.php`.
Tests: `Tests/Integration/Generation/ComponentPrototypeScreenTest.php`,
`Tests/Integration/Generation/MakeComponentToolTest.php`. The test container
(`var/cache/test`) is rebuilt after a change to a constructor or a route.
