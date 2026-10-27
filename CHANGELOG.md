# Changelog

All notable changes to this module are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this module adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added (2.2.0)

- **Writing instructions for a page template.** A page template gains
  **Writing instructions** next to its brief: how every page of it is
  written (tone, length, expected sections, mandatory mentions). The
  generator reads them as rules for every page of the template, after its
  brief, whether it writes, revises, rewrites a section or translates; they
  travel in the export. The update to 2.2.0 adds `cms_page_type.instructions`;
  `PageTypeWriter::describe()` takes them as a last, optional argument.
- **Two skills: translation and the design system.** `translation`, always
  read: a page written from another language keeps its meaning, its order,
  its names and its figures as written, and follows the typography of its
  language. `design-system`, always read: every page uses the components of
  the catalogue rather than free text imitating them, with no style of its
  own. Existing sites receive them on the next read of the skills.
- **Reordering the skills from their list.** **CMS > Skills** shows arrows
  that move a skill one slot up or down, and numbers the positions again from
  1; the generator reads the skills in that order.
- **Documentation in the back office.** **CMS > Documentation** tells an
  editor, in French or in English after the language of the back office, what
  the module lets them do: pages and their addresses, content and the
  generator, page templates, tags, the content of catalogue items, components,
  skills, menus, forms, media, settings and permissions. It takes the right to
  see the pages. Every screen of the CMS links to the section explaining it.
- **Fixed addresses.** A page can keep, in a language, a whole address that
  does not follow the tree (`about-us.html`), typed in the new **Fixed
  address** field of its edit screen: a site taken over keeps the addresses
  search engines know. Rebuilds keep it, it travels in the export, and the
  update to 2.2.0 adds `cms_page_i18n.fixed_url`.

### Added (2.1.0)

- **A document for each catalogue item.** A product, a category, a content
  and a folder carry a document of sections in each language, written on the
  content screen of a page (from a brief, by asking or by hand) and reached
  from a **CMS content** tab of the edit screen of the item. A theme prints it
  with `cms_item_content(type, id)`. The update to 2.1.0 adds the
  `cms_item_content` and `cms_item_revision` tables (docs/adr/0011).
- The documents of the catalogue items count as uses of their images and
  components, travel in the export (section `catalogue_contents`) and go back
  on import on the item with the same reference or address.
- The preview of the document of an item shows the **whole page** of the item
  with the draft in place, or the **content only**, without the header and the
  footer of the theme. A list sets the width of the preview to a breakpoint of
  the theme, as its toolkit does.

### Changed (2.0.0)

- A record or a picture the PHP class of a theme component takes
  (`{# @prop productId product #}`, documented and not declared in
  `{% props %}`) is required unless its type says `|null`: a product card
  saved without its product rendered an empty card. Scalars the class takes
  stay optional. A page holding such a section without the record keeps
  rendering, and is refused at its next save until the record is picked.
- **Blocks are gone.** The reusable blocks (**CMS > Blocks**) and the blocks
  of catalogue items (the "Content block" card of a product, a category, a
  brand or a store) are removed, with their tables: the update to 2.0.0 drops
  `cms_block`, `cms_block_i18n`, `cms_block_content` and `cms_item_block`.
  A theme that called `cms_item_block()` removes the call. An export taken
  before the update keeps the blocks; a 2.0.0 module imports the rest of the
  file and warns that the blocks were left out.
- **Saved page templates are gone.** The **CMS > Templates** screen, the
  `cms_page_template` table (dropped by the update to 2.0.0) and the code that
  saved a page as a template and started a page from one. A page is started
  from a brief, or duplicated from another page.
- **The content of a 1.x site is not carried over.** The content of a page
  is now a document — an ordered list of sections, each a component
  of the catalogue with its props — rendered to HTML on save and on publish.
  The markup the former editor stored has no document to be read back from, so
  the update to 2.0.0 puts every page back to draft, without content,
  and removes their revisions and search entries. Pages
  keep their tree, addresses, SEO, tags and types. **Export the site before
  updating** (`php Thelia thelia_cms:export`): the file keeps the HTML, and a
  2.0.0 module imports everything in it but the contents.
- `cms_page_content` carries `draft_document` and
  `published_document` and lose `draft_project_data`, `draft_css` and
  `published_css`; `cms_page_revision` carries `document` and loses
  `project_data` and `css`. A page has no stylesheet of its own any more: its
  look comes from the components it is made of and from the stylesheets of the
  site.
- The export format is version 2: contents are `draft_document`, `draft_html`,
  `published_document`, `published_html`. A version 1 file still imports its
  pages, menus, forms, settings and media, with a warning that the contents
  were left out.
- `TheliaCMS\Page\PublishedPage` loses `css` and the `layout` deprecated in
  1.2.0. A theme that printed `cms_page.css` removes the line.
- The heading check (`heading_check_mode`) runs wherever a page goes online —
  the editor, the settings screen, `thelia_cms:publish` — and no longer in the
  editor alone. In `block` mode the writer refuses the page with
  `HeadingStructureException`; in `warn` mode the editor still shows the
  problems after publishing.
- `CmsPageWriter::saveContent()` takes a
  `TheliaCMS\Content\Document`; `BuilderContent` is gone. A document whose
  sections name no component of the catalogue, or carry props a component
  refuses, is rejected with `InvalidDocumentException` listing every problem.
- Unpublishing a page drops its published snapshot along with the date.

### Added (2.0.0)

- **A page under Flexy is laid out like a content of the shop**
  (docs/adr/0010): the module ships `templates/frontOffice/flexy/cmspage.html.twig`
  with the heading band of the theme (title, summary, breadcrumb), the fields
  of the page type, then the sections. Every template of a page draws its
  title as the level 1 heading, the fallback of the module included: the
  generator no longer writes an `<h1>` nor opens with a heading band, the
  heading check reports a level 1 heading in the content of a page, and the
  legal pages are seeded without one.
- **A page type is a template of pages** (docs/adr/0009). Its screen under
  **CMS > Page templates** (formerly **CMS > Settings > Page types**, the
  same screen, now also in the menu) names it in each language, gives the brief
  the generator reads before the brief of every page of the type, defines
  the fields its pages carry (whole number, decimal, duration, short or long
  text, yes or no, a word from a list, a date; with a unit, bounds, one value
  per language for a text, required to publish) and creates its model page,
  the demo content written like any page and never published. **Start a
  page from a template** on the page list copies the model under the title
  typed; the "Fields" tab of a page holds its values, which the generator
  writes along with the content (a `fields` string in the envelope, checked
  and sent back for correction like the props). A theme reads
  `cms_page.fields.<code>`, `cms_page_fields(page)` and lists the pages of a
  type with `cms_pages_of_type(type, {sort, direction, filter, limit})`; an
  editor places the list with the **Pages of a type** section
  (`Partial:cms-page-list`). The description of the types, the model flag
  and the values travel with the site export. The update to 2.0.0 adds
  `cms_page_type_i18n`, `cms_page_type.brief`, `brief_tone`,
  `brief_audience` and `fields`, `cms_page.model` and `fields`, and
  `cms_page_i18n.translated_fields`. A page of a type left without a
  required value is refused at its publication, the model of a type always.
- A section may carry an appearance besides its props: a background, a
  colour of text (`#rrggbb`), room above and below (`small`, `medium`,
  `large`) and an alignment. A brief or a change asks for it in words ("on a
  pastel red background") and the generator writes the values; the
  **Appearance** group of "Edit by hand" sets them too. Nothing else about
  the look of a section can be set: the values are checked when the document
  is read, a colour written otherwise is sent back to the model to be
  corrected, and the module writes the style itself. A section with an
  appearance is wrapped in a `div.cms-section-styled` of the whole width that
  carries `data-cms-section` and the style; a section without one renders and
  is stored as before. A heading a component colours itself (`text-black`)
  keeps its colour. A translation keeps the appearance of each section of
  the source, whatever the model writes back. In "Edit by hand", a colour
  picker sits next to each colour field and writes into it.
- "Edit by hand" and "Rewrite this section" open in a dialog rather than
  under the section in the list.
- **Components made in the back office.** **CMS > Components > New
  component** asks the generator for a component in a prompt; the site checks
  it, draws its sample in the theme at the width of a phone, a tablet or a
  screen, and keeps it once the editor says so, after as many changes as
  asked. A kept component is `Site:<Code>`, a component like any other:
  offered to the generator of pages, edited by hand, on the toolkit page,
  switched off on the list. It is props as data, a Twig template rendered in
  a sandbox of its own and given the props alone, with no text of its own,
  and a stylesheet scoped to the component and served at
  `/cms/site-components.css`. A new version that would break the pages using
  it is refused, the pages named; keeping one draws their drafts again. A
  component in use cannot be deleted. Making one takes the settings and the
  custom code permissions. Stored in `cms_component` (created by the update
  to 2.0.0); the calls go in the ledger with the pages; the components
  travel with the export of the site and are checked again on import.
- A **Columns** section (`cms:columns`): two to four columns side by side,
  each an image over an optional heading and a short text, as many columns as
  items. The image is one of the media library or the `https` address of a
  picture kept elsewhere; the text is written as text. The module draws it
  with a grid of its own (`blocks.css`), one column on a narrow screen, so it
  needs nothing from the theme. A brief asking for "three columns with a logo
  and a short text" now has a section to write it in.
- An **Image** section (`cms:image`): one picture on its own, of the media
  library or the `https` address of a picture kept elsewhere, with an
  optional caption and three widths (reading column by default, content,
  whole screen).
  Free text holds no image: a brief asking for "a photo of the tart out of
  the oven" now has a section to put it in.
- The generator says what a draft leaves out of the brief: an image the
  media library does not hold and the brief gives no address of, a product
  the search does not find, a page that could not be read. The answer
  carries an optional `left_out` list, stored in a new column of
  `cms_page_generation` (added by the update to 2.0.0), shown as a warning
  that stays until it is closed when the draft comes back, in the history of
  the page and by `thelia_cms:generate`. A brief asking for a photo the site
  does not have used to give a page without it, and no word about it.
- `thelia_cms:render` renders the drafts of pages again from their documents,
  without publishing them (`--page`, `--all`, `--locale`): a change of the
  module or of the theme reaches every draft at once instead of at its next
  save. A draft whose custom code the new rendering would take out is left
  alone and listed, the command holding nobody's right to it.
- `thelia_cms:publish`, `thelia_cms:generate` and `thelia_cms:render` render a
  page holding a component of the theme that reads its records through the
  API (a product card): from the command line, it failed with "No current
  request found", then "URL instance is not initialized". The commands run
  the rendering as a visit to the address of the shop, `url_site` or `--url`;
  without one, the page fails with a message naming them.
- A theme names the classes it styles written text with,
  `sections.richtext_class` in its `config/theliacms.yaml`, and free text
  sections carry them next to `cms-richtext`: on Flexy, `wysiwyg
  wysiwyg-theme` gives their lists and headings the look of the other texts
  of the shop. A page saved before keeps its old rendering until its next
  save or publication.
- Once a page has a brief, the content screen recalls it in three lines,
  with its tone and audience, and **Change the brief** opens the form in a
  dialog: the room goes to the sections. A page without a brief keeps the
  form in the screen.

- A brief may cite web pages by their address: the generator offers the
  `web_fetch` tool of the Claude API for that request, and the model reads
  them before it writes (five pages at most, 20,000 tokens each). A brief
  that cites none sends the request it always sent. The pages are read by
  Anthropic, never by the server of the shop; the tool has to be allowed
  for the organisation of the key, and **Read the web pages a brief cites**
  in the Generator card of CMS > Settings turns it off (`generator_web_fetch`,
  on by default). The history of the content screen lists the pages a call
  read, with the tokens of each and what one reading costs; the ledger keeps
  them in `cms_page_generation.web_pages`, which the update to 2.0.0 adds.
- A theme gives a component the props it is shown with under
  `sections.examples` of `config/theliacms.yaml`: the toolkit, the generator
  and a section added by hand start from them. For a component whose defaults
  render nothing, such as Flexy's `Organisms:VideoPlayer:Base`.
- Hovering a section in the preview of the content screen shows **Edit**,
  which opens the form of that section in the list.
- **Add a section** in the preview of the content screen, after the last
  section: a component the site offers is added at the end of the draft with its example props, then
  edited by hand. A page with no content yet can be started that way, without
  the generator.
- The sections of a draft are **reordered by hand** on the content screen,
  dragged by their handle or moved one place up or down with the arrows, with
  the right to change pages. The move is
  saved in the draft and written to the ledger like any change by hand.
- **CMS > Components** lists the components of the catalogue, where each
  comes from and how many pages use it, and switches one off for the
  generator. A component switched off is left out of what the generator reads
  and may write, except on a page that already uses it; pages keep rendering
  it. `SectionCatalog::offered()` is the catalogue the site offers,
  `SectionCatalog::all()` stays everything a page may name.
- `TheliaCMS\Content`: `Document`, `Section`, `SectionDefinitionInterface`
  (implementing it registers a component), `SectionProviderInterface`,
  `SectionCatalog`, `Prop` and `PropSchema` (typed props, checked on the way
  in, described as a JSON schema), `DocumentValidator`, `DocumentRenderer`,
  and the one built-in component, `cms:richtext`, a piece of free markup held
  at the width of the sections by `.cms-richtext`. See `docs/sections.md`.
- Every partial of the site is a section, `Partial:<name>`, with the settings
  of the partial as props and the placeholder the page serves through as
  rendering.
- A theme opens its Twig components to pages with `sections.components` in
  `config/theliacms.yaml`; their props are read off the `{# @prop #}` comments
  and the `{% props %}` tag of the template, and they render through the
  component renderer of Symfony UX. Nothing is opened by default.
- `cms_section(name, props)` renders a section from a template, with its
  sample props when none are given. Every section of the catalogue is listed
  on one page of the toolkit of the Flexy theme, under "CMS".
- **The generator** (`TheliaCMS\Generation`): a page is written from a brief
  by the Claude API, as a document constrained to the JSON schema of the
  catalogue, validated and rendered by the site. The model reads the skills
  and the catalogue (cached), may search the products, categories, pages and
  images of the site, and sends an invalid document back once. Every call is
  written to the ledger `cms_page_generation` with its tokens and an estimate
  of its cost. The key is read from `ANTHROPIC_API_KEY`; the model, the effort
  and the token limit are settings. Command `thelia_cms:generate`. See
  `docs/generator.md`.
- **The content screen** (`/admin/cms/pages/{id}/content`): the brief on the left, the draft on the right as the theme
  shows it; a form to rewrite each section alone, one to ask a change on the
  whole draft, the history of the calls with their cost, and the publish
  button. The forms post in the background, the progress of the call is
  streamed as server-sent events, and the screen is drawn again in place
  when the call ends, without a reload; without the script, every action
  still posts and comes back with a message. "Edit content" everywhere in
  the back office now leads here rather than to the visual editor.
- On the content screen, every section is also **edited by hand**: a form
  read from the schema of its component, the `html` props in the editor of
  the Tiptap module when it is there (`thelia/tiptap-module`, suggested),
  lists as rows added and removed, images from the media library, references
  from their picker. Stored through the writer like a generated document and
  written to the ledger as a `manual` row, so the next request starts from
  the corrected text. Costs nothing, takes the right to change pages. Saved
  in the background, the screen drawn again in place with the panel open.
- `GenerationTarget`: what the generator writes for, a page.
  `GenerationObserverInterface` reports each turn to whoever listens.
- **Skills**: the instructions the generator reads, shipped as
  `skills/<code>/SKILL.md` by the module (five) and the theme, sown into
  `cms_skill` at the first read and edited there by the site, under
  **CMS > Skills** (the settings right): rewrite, switch off, reorder, put
  the shipped text back.
- The **Generator** card of CMS > Settings: the model, the effort and the
  token limit.
- The answer of the API is read as a stream: the screen shows how much of
  the document is written as it comes (`GenerationObserverInterface::onText`,
  event `delta`). Measured on 2026-10-20: an answer constrained to the schema
  is delivered by the API in a burst at the end of the turn (first delta at
  4.3 s, last at 5.3 s on a 5.3 s call), so the turns stay the progress a
  reader sees; the stream pays off on the day the API sends constrained
  answers as they are written.
- The tool `search_forms`, so a contact page finds the form of the site
  rather than guessing its id.
- An `image` prop holds the id of a library image or the `https://` address
  of a picture kept elsewhere; the hand-edit form previews the picture,
  replaces it by a file that joins the media library, or takes an address.
- **The components of the theme come first.** A `@prop` of a theme
  component may name what the site holds — `image`, `product`, `category`,
  `folder`, `form`, `menu`, or a list such as `product[]` — and the
  parameters the PHP class of the component takes (`productIds`) are read
  when they are documented; the component receives ids, as a template of the
  theme would pass them. The theme puts the module's sections it replaces
  away under `sections.hidden` of `config/theliacms.yaml`, and the
  `catalogue` skill sends the model to a component of the theme before a
  block of the module. With that, Flexy's `Layouts:CrossSelling`,
  `Organisms:VideoPlayer`, `Organisms:CategoryCard` and the like are used on
  a page as they are, without a theme template over a partial of the module.
- The toolkit of the Flexy theme gets a page with every section of the
  catalogue (`/toolkit/cms-all-sections`, `cms_catalogue()` in Twig), and
  the content screen links to it in debug, so a brief names what it wants.
  Every CMS story of the toolkit now loads the stylesheet of the sections.
- On the content screen: the brief the page was last written from is back
  in its form (the ledger keeps it, column `brief` of `cms_page_generation`);
  every section has a number, a button that scrolls the preview to it and a
  button that removes it; the preview and the list point at each other. A
  page that holds a draft in another language and nothing in this one offers
  to be written from that draft (`GenerationRequest::translation`, kind
  `translation` in the ledger) rather than announcing an empty page. The
  renderer marks the first element of every section with
  `data-cms-section="<id>"`.
- `docs/adr/`: the eight decisions behind the generation of pages;
  `docs/evaluation/`: ten briefs, a rubric and the scores of the shipped
  skills (7.1 / 8 on 2026-10-20).
- The right `admin.cms.generate`, given to the seeded editor profile.
- The module requires `anthropic-ai/sdk` and `nyholm/psr7`.

### Removed (2.0.0)

- **The visual page builder** (GrapesJS, `openstudio/page-builder-bundle`):
  the screens `/admin/cms/pages/{id}/builder` and
  `/admin/cms/blocks/{id}/builder`, their bundled editor, the routes
  `openstudio_page_builder_image_*` the editor uploaded and listed images
  with (the media screens of the module have their own), the "Blocks offered
  in the editor" card of the settings with the `disabled_blocks` setting (the
  catalogue a page is made of is the allowlist of the theme, see
  `docs/sections.md`), the canvas stylesheet of the theme
  (`config/theliacms.yaml` key `editor`, `builder_stylesheet`) and
  `docs/editor-canvas.md`. The module needs no npm build any more.
- `TheliaCMS\Builder`: `CmsBuilderConfig`, `ThemeCanvas`, `ActiveThemeCanvas`,
  `CanvasStylesheetProviderInterface`, `BlockCatalog`, `CoreBlockCatalog`,
  `CatalogBlock`, `CatalogBlockProviderInterface`, `EditorBaseBlocks`,
  `Admin\CmsPageBuilderController`, `Admin\CmsPageContentType`;
  `TheliaCMS\Block\Admin\CmsBlockBuilderController`;
  `TheliaCMS\Media\LibraryImageUploader`, `LibraryImageCatalog`,
  `Admin\CmsMediaController`; `TheliaCMS\Settings\DisabledBlocks` and
  `CmsSettings::disabledBlocks()`; `PartialRegistry::toEditor()`;
  `PageContentNormalizer::css()`. The export format no longer carries
  `disabled_blocks`; a file that does is read without it.
- The site styles screen (`/admin/cms/settings/styles`, closed since 1.4.0)
  and the colour palette of the editor (`builder_palette`). The typography
  of the site is still served (`SiteStyles::typography()`).
- The webmaster guide (`docs/guide/`, PDF and screenshots): it described the
  editor of 1.x. A guide for the content screen is to come.
- **Blocks**: `TheliaCMS\Block` (screens, writers, repositories, the hook of
  the "Content block" card, the purge on deletion of an item), the models
  `CmsBlock*` and `CmsItemBlock*`, `Generation\Admin\CmsBlockContentController`,
  `Preview\CmsBlockPreviewController` and `PreviewLink::urlForBlock()`, the
  Twig function `cms_item_block()`, the source `admin.cms.partials.sources.blocks`
  and the `block` type of a `@prop`, `GenerationTarget::block()` and the
  components a target excludes, `CacheTags::block()` and `::item()`,
  `PartialCache::invalidateBlocks()`, the blocks of the export format.
- The partials a component of the theme replaces: `Partial:cms-block`,
  `Partial:product-card`, `Partial:product-strip`, `Partial:category-card`,
  `Partial:category-strip` and `Partial:video-facade`, with their templates,
  their styles in `blocks.css`, `Partial\Catalogue\ProductSummary`,
  `ProductCardPrice`, `CategorySummary`, `CatalogueImage` and the image
  source `admin.cms.partials.sources.images`. A product, a category or a
  video on a page is a component of the theme (`sections.components`).
- **The page blocks of the module** (hero, text and image, call to action,
  quote, testimonials, key figures, logos, gallery, questions and answers,
  section) have no section of their own: a page is built from the
  components the theme opens, free text (`cms:richtext`) and live content.
  `blocks.css` keeps the styles of the free text, the partials and the forms.
  A module adds a component by implementing `SectionDefinitionInterface`
  (see `docs/creating-a-component.md`); there is no `AbstractTemplateSection`
  and no `cms/sections/<slug>.html.twig` to take over.
- The click-to-load embeds: `Partial:map-facade` and `Partial:social-facade`
  (`MapFacadePartial`, `SocialFacadePartial`), the shared `_facade.html.twig`
  and its script, and their styles. A map or a social post on a page is a
  component of the theme. An iframe written in a page is still refused unless
  the author may write custom code.
- `Partial:latest-contents` and `Partial:cms-menu` (`LatestContentsPartial`,
  `MenuPartial`), with their templates and their styles. The live content of
  the module is a form; the latest news or a menu on a page is a component of
  the theme. `cms_menu()`, the footer menu hook and the menus themselves are
  untouched.
- Saved page templates: `ImportExport\Admin\CmsTemplateAdminController`,
  `PageTemplateWriter`, `SaveAsTemplateType`, `PageFromTemplateType`, the
  models `CmsPageTemplate*`, `SiteExporter::exportPage()` and
  `SiteImporter::importPageFrom()`. `Page\PageTemplateResolver`, which picks
  the Twig template of a page by its type, is unrelated and stays.
- The pipeline a page goes through before it is stored or published moves
  from `TheliaCMS\Builder` to `TheliaCMS\Content\Pipeline`:
  `HeadingChecker`, `EmptyContentChecker`, `PlaceholderContentChecker`,
  `ImageRewriter`, `PublishedContentSanitizer`, `PageContentNormalizer`.

### Added (1.5.0)

- On a Thelia core that brings the shared cache of the public pages
  (`shared_cache_mode`), the pages of the module are shared like the
  catalogue pages once the shop turns it on: the `cmspage` view, and so the
  home page the module takes over, is declared to the core, which then
  decides whether an answer may be kept and writes its headers. The module
  steps aside from its own `Cache-Control`, `Cache-Tag` and `Surrogate-Key`
  while the core shares, and files every page under `cms` and `cms-menu`
  (the menus and settings of the module can show on any page), a page of the
  module under `cms-page-<id>` as well. Every purge of the module (a page
  published, a menu or the settings saved, a block edited) is handed to the
  proxy of the core, and publishing the block of a catalogue item purges the
  page of that item (`product-42`, `category-7`, `brand-3`). On an older core
  nothing changes: the module keeps its own headers and `http_cache_ttl`.
  Publishing a page purges `cms` like before, which on a shop that shares
  means every page.

### Upgrading to 1.4.0

- The update adds the `cms_item_block` table. Update the module before
  opening a catalogue screen, and remove `var/propel/<env>` so the models are
  generated again.
- Going back to 1.3 after the update:

  ```sql
  DROP TABLE `cms_item_block`;
  ```

### Added (1.4.0)

- **Product** and **Category** blocks put one item of the catalogue on a
  page, searched by the editor (a product by reference, name or barcode, a
  category by name, shown with its parent) among the items online. The page
  stores the id, the item is read on every visit and the block leaves the page
  once the item goes offline. The module's cards show the picture, the name,
  the taxed price of the default sale element and a link; a theme draws its own
  through `cms/partials/product-card` and `cms/partials/category-card`.
- A reference setting of a dynamic block can be `searchable`: the editor
  shows a search box rather than a select holding the whole source, and the
  source answers `?q=` with what matches and `?id=` with the record chosen.
  Made for sources too long to list at once, such as the products of a
  catalogue. See "Picking from a long list" in `docs/creating-a-block.md`.
- A product, a category, a brand and, when the Dealer module is active, a
  store can each carry one content block: a free layout built with the page
  editor, the way a reusable block is, and shown by the theme on the page of
  the item. The block is created, opened and deleted from a "Content block"
  card in the right column of the product, category and brand screens, and
  from a tab of the same name on the store screen; its builder leads back to
  the item. It is published per language like any block, and the theme reads
  it with `cms_item_block('product', product.id)`, which answers the published
  HTML and CSS or null — or from PHP with `PublishedItemBlockRepository`. The
  dynamic blocks of a page are offered in it, the reusable block excepted.
  The block goes with its item when the item is deleted. The blocks of items
  are kept out of **CMS > Blocks**, of the reusable blocks a page may embed
  and of the site export, and an import leaves their codes alone. Editing
  them takes the right to edit pages, on any item. The module purges the
  cache of its own pages only: the page of the item follows its HTTP cache.

### Upgrading to 1.3.0

- The update adds the `cms_tag` table and `cms_page.tag_id`, with a constraint
  that detaches the pages of a deleted tag. Update the module before serving
  pages, and remove `var/propel/<env>` so the models are generated again.
- Going back to 1.2 after the update:

  ```sql
  ALTER TABLE `cms_page` DROP FOREIGN KEY `fk_cms_page_tag`, DROP COLUMN `tag_id`;
  DROP TABLE `cms_tag`;
  ```

### Added (1.3.0)

- A page carries one tag or none, picked in the "General" tab among the tags
  of the site, which are listed, added, renamed and deleted under
  **CMS > Tags** with the right to edit pages. A tag is a name shown as it is
  in every language. Deleting a tag leaves its pages without one, the bin
  included, and the screen says how many. Themes read the tag of the page
  served as `cms_page.tag`, the modules hooked on `cmspage.*` as `page.tag`,
  and list the pages online carrying a tag with `cms_pages_tagged(tag)`, the
  most recently published first; from PHP,
  `PublishedPageRepository::withTag()` answers the same. The tag shows in the
  page listing, follows a page when it is duplicated, travels with the site
  export and with saved templates, and an import creates the tags the site
  lacks. Files written before 1.3.0 import pages without a tag.

### Upgrading to 1.2.0

- The update renames `cms_page.layout` to `page_type` and adds the
  `cms_page_type` table. Update the module before serving pages, and remove
  `var/propel/<env>` so the models are generated again: until both are done,
  every CMS page fails on `Unknown column 'page_type'`, or on `layout` the other
  way round.
- Going back to 1.1 after the update needs the column back under its former
  name, kept at 50 characters so no code is cut. Export the database first:

  ```sql
  ALTER TABLE `cms_page` CHANGE COLUMN `page_type` `layout` VARCHAR(50) NOT NULL DEFAULT 'default';
  DROP TABLE `cms_page_type`;
  ```

### Added

- A page has a type, which picks the template it is displayed with:
  `cmspage-{type}.html.twig` from the theme, then from this module, before the
  usual `cmspage.html.twig`. The types are listed, added and deleted under
  **CMS > Settings > Page types**, which says for each one the template it
  gets. A type is a code and nothing else; the three former layouts become the
  types `default`, `full-width` and `landing`, and each page keeps its own. A
  type a page has, the bin included, cannot be deleted. Themes read the code as
  `cms_page.pageType`, the modules hooked on `cmspage.*` as `page.pageType`.
  The type follows a page when it is duplicated, travels with the site export
  and with saved templates. An import of a site creates the types the site
  lacks; a page started from a saved template whose type the site no longer
  has gets the default type, since starting a page needs no right on the
  settings. Files and templates written before 1.2.0 are read through their
  `layout`.
- A page carries a summary and a detailed description per language, the same
  pair as a core content, typed under its address in the "General" tab with
  the back-office rich text editor when Tiptap is installed. They are not the
  body of the page, which the builder owns, and nothing renders them on their
  own: a theme reads them as `cms_page.chapo` and `cms_page.description`, and
  the modules hooked on `cmspage.*` as `page.chapo` and `page.description`.
  Both are HTML filtered on save like the content of the page: no script, and
  no iframe without the custom code right; an imported file gets no iframe
  either. Both follow the page when it is duplicated and travel with the site
  export.
  The update to 1.1.0 adds the two columns to `cms_page_i18n`.
- An "Image" tab gives a page one image, the same in every language, chosen
  from the CMS media library or uploaded into it, and saved with the rest of
  the page. Uploading takes the right to create media, as on the media screen:
  without it the tab only offers the images already in the library. A theme reads it as `cms_page.image` (`url`, `alt`, `width`,
  `height`); an image deleted from the library leaves the page without one.
  It follows the page when it is duplicated, travels with the site export
  like the images of the content, and counts as a use on the media screen, so
  the image of a page cannot be deleted from under it. The update to 1.1.0
  adds `image_id` to `cms_page`.
- **CMS > Settings** lists every block the editor can offer, group by group as
  the panel shows them — the basic, layout and advanced blocks of the editor
  itself, the page blocks of the catalogue, the live content — and lets an
  administrator untick the ones a site has no use for. Until now every block
  was offered to every editor, so a theme that styles no gallery still had a
  gallery in the panel, and the "Video" and "Map" blocks of the editor sat next
  to the facades that replace them. A block is offered until it is unticked,
  which is why the setting stores the blocks that are off: one a module or the
  editor adds later shows up on its own. Unticking changes the panel and
  nothing else: a page that already holds the block keeps it, previewed and
  editable in the editor, rendered on the front. The list travels with the
  site export as `disabled_blocks`.
- A theme declares the canvas of the editor in `config/theliacms.yaml`: the
  stylesheets it loads, written the way its templates pass them to `asset()`,
  and the classes of `<html>`, `<body>` and of the container around the page
  content. The canvas loaded one stylesheet and wrapped the page in
  `cms-page-content` only, so a theme styling its text through a class on
  `<html>` or through its own content container was previewed in the wrong
  font and without its columns. Declared stylesheets replace `styles/app.css`
  and are resolved at every opening, so a new build is picked up; the
  container classes are shown in the canvas and never saved with the page. A
  child theme inherits what it does not declare. Without the file, nothing
  changes. See `docs/editor-canvas.md`.
- `CanvasStylesheetProviderInterface` hands the editor canvas the stylesheet
  of the blocks a module contributes. The canvas only loaded the socle of the
  catalogue and the theme, so a block from another module was laid out once
  published and unstyled while an editor dragged it in. The stylesheet is
  loaded after the socle and before the theme, as on the front.
- `footer_menu_hook` renders the `footer` menu of the CMS into the footer of a
  theme that never calls `cms_menu('footer')`. Until now a CMS page on such a
  theme was reachable by its address alone: the footer of the demonstration site
  listed five native contents and no CMS page. Off by default, since a theme
  that already renders the menu would show it twice and nothing in a template
  says whether it does.
- The module ships the stylesheet of its own block catalogue, served on
  `/cms/blocks.css` and linked on every page. A page opened on a theme that
  knows nothing about the CMS used to come out as unstyled markup, which is what
  happened on the demonstration site: the socle lived in the companion theme,
  and nothing else had it. Colours go through `--cms-*` tokens that borrow the
  palette of the theme when it names its colours the way Flexy does, and fall
  back on neutrals otherwise. The socle sits in a cascade layer, so any rule a
  theme writes wins over it whatever order the stylesheets load in, and the
  companion theme now keeps nothing but its four accent tokens and one rule of
  its own.
- An address that differs from a real one only by a trailing slash answers 301
  towards the form without it. It used to answer 404, which costs a site taken
  over from WordPress, Drupal or Prestashop nearly every address it had indexed:
  those serve theirs with the slash. Nothing is redirected unless the address
  without the slash answers, the query string travels, and the root, the back
  office and the API are left alone. Decided on the request, above the router:
  with `allow_slash_ended_uri` on, `/contact/` otherwise reached the `/{_view}`
  catch-all of the Front module and rendered the contact template of the theme
  with a 200, so no 404 was ever thrown and the page answering on `/contact` was
  never reached.
- `cms_is_showcase()` in Twig, so a theme can leave out what a showcase site does
  not answer for. The cart and the checkout answer 404 in that mode, and the
  header of the companion theme linked to them all the same: a dead link on every
  page of the site, found on a real takeover.
- The slug of a page is stored per language, next to its title. Until now it only
  existed inside the address, in a core table the module has to clear when it is
  switched off. Existing sites keep the addresses they answer on: the migration
  reads each slug back from the address in use.
- `thelia_cms:publish` puts drafts online from the command line, through the same
  pipeline as the back-office button: sanitiser, responsive images, search index
  and revision. A site whose content has just been imported or rewritten in bulk
  had no way to publish other than clicking each page, and writing the published
  column from a script skips all four steps without saying so. Takes `--page`,
  `--all`, `--locale` and `--dry-run`, and reports the pages it refused.
- The page list and the showcase dashboard name the pages that are online with
  the example text of the seeded legal pages still on them. Publishing one of
  those is refused, but a site set up before that refusal existed has them live,
  in its sitemap and in search results, and nothing said so: seven of them were
  found that way on a real site. Each one is a link to the page and the language
  that needs writing. The lookup asks the database for a single word of each
  sample sentence and then confirms the answer with the same check publishing
  uses, so it never reads the published content of the whole site.
- The accessibility suite signs in and scans the page listing, the results of a
  search, the results in each publication state and the bin. It also checks the
  two things a scan cannot: that a branch of the tree opens with the keyboard
  alone, and that no control of the screen is smaller than the 24 by 24 pixels
  WCAG 2.2 asks for. Colour contrast is judged against the palette of the
  back-office theme, which fails on every screen of it, so what the test refuses
  is a colour pair this screen adds.

### Changed

- The constructor of `PublishedPage` takes `string $pageType` where it took
  `PageLayout $layout`. The module is the only one meant to build that object;
  a hook reading `$layout` keeps working.
- Saving the settings of a page drops it from the shared cache. Its type, its
  title and its SEO fields are live as soon as they are saved, and a cached copy
  kept showing the previous ones until it expired.
- Every write of the back office under `/admin/cms` requires the back-office
  token, in the `_token` field of the body or in the `X-CSRF-Token` header. A
  script of your own that posts to these routes now gets a 403 until it sends
  the token. Saving a block also needs the right to change pages.
- **CMS > Pages** shows the tree branch by branch, with a search box and filters
  on the publication state and on the visibility. It used to list every page of
  the site flat, with nothing to search: on the pilot of a takeover that meant 128
  rows, 100 of them nested, so the structure of the site was unreadable and
  finding a page meant scrolling. A page holding others now carries the number it
  holds and opens on a chevron, what is open travels in the address, and a search
  gives a flat list of fifty results at a time where each row names the pages it
  sits under. Reordering is offered on the tree only.
- A site of fewer than forty pages opens whole, so nothing changes for a site that
  never needed folding.

### Deprecated

- `CmsPageRenderer::THEME_TEMPLATE` and `MODULE_TEMPLATE`: the template of a
  page now depends on its type, which `PageTemplateResolver` resolves. Both go
  in 2.0.0.
- `PublishedPage::$layout` and the `PageLayout` enum, replaced by
  `PublishedPage::$pageType`. `layout` is still filled for the hooks written
  against 1.x, from the type: `full-width` and `landing` read as before, any
  other type as `Default`. Both go in 2.0.0.

### Fixed

- The media screen counted a page once per language using an image, so an
  image on a page in French and English read "Used in 2 page(s)".
- The page form no longer raises a Symfony deprecation each time it is built.
  Its canonical URL field left the default scheme unset; a canonical address
  typed without its scheme is now read as `https://`, as in SEOne.
- Deleting an image from the image library of the editor works. The editor
  sent the deletion to an address no route answered, so it got a 404 and the
  image stayed in the library. The deletion now follows the rules of the media
  screen: an image a page or a reusable block still shows is kept and the editor
  says why, and only images of the CMS can be deleted, not those uploaded for a
  product.
- The media screen keeps an image a reusable block still shows. It only looked
  at the pages, so deleting the image left a broken picture on every page using
  the block.
- The `srcset` of a published image offers the file at its original width. It
  only listed the fixed widths below it, so an image of 900 px shown at 1440 px
  was served at 480 px and came out blurred; the same held for every image
  between two widths. The original is the last candidate of the `<img>` and of
  each `<source>`, never repeated when it equals a fixed width, and nothing is
  enlarged past it.
- A menu entry pointing at another site is never current: an external link
  served on `/` was marked current on the home page. An entry pointing at the
  home page links to the root of the site, where the page is served, instead of
  its own address, which redirects there; it is current on the home page now.
  Choosing another home page drops the cached menus.
- An import file listing the choices of a form field (`"choices": ["A", "B"]`)
  imports like one writing them as text. The list reached the model as an
  array and stopped the whole import on a type error.
- A menu entry that carries a query string is current only when the page has
  the same parameters. Entries pointing at `/search?q=recipes` and
  `/search?q=labels` were both marked current on any search.
- The canvas of the editor loaded no stylesheet of the block catalogue, so an
  author built a page as a stack of unstyled paragraphs while the published page
  came out laid out. It is handed the same sheets as the front now, in the same
  order: the socle of the catalogue, the stylesheet of the theme, then the styles
  configured for the site.
- Opening a page in the editor and saving it could empty it. A project saved
  while the canvas was empty is stored as `{"pages":[]}`, and the editor loaded
  it, dropped the markup the canvas had been given, then wrote that emptiness
  back over the HTML and the CSS of the page on the next save. Such a project is
  now read as nothing to restore, so the canvas keeps the markup it was handed,
  and the CSS of a page written outside the editor is loaded with it instead of
  being overwritten. The second half of the fix belongs to
  `openstudio/page-builder-bundle` and is already in the compiled editor shipped
  here, so nothing waits on a release of the bundle.
- The tables of the module are created with the same storage settings as the core
  ones (`utf8mb4`, `utf8mb4_general_ci`, DYNAMIC row format) instead of taking
  whatever the database server defaults to. On a host whose default is not
  `utf8mb4`, a join between a page and a core table was refused outright (MySQL
  error 1267, illegal mix of collations) and anything the server charset does not
  cover was truncated on the way in. Sites already installed are converted table
  by table, and a site already in `utf8mb4` pays nothing.
- The migration that reads a slug back from the address it answers on compared the
  page identifier as text, through a cast that carries the collation of the
  connection. As soon as that collation differed from the one of the column, the
  statement was refused and the migration ran without filling a single slug.
- The block panel of the editor read French on an English back office. The panel
  is filled while the editor still runs in the language the bundle ships as its
  default, and a block keeps the label it was drawn with, so every block and
  every category sat in the wrong language next to a screen in the right one. Two
  labels the bundle spells out in French whatever the locale, the list block and
  the icon setting, now come from the server like the rest of the wording.
- The name of a block of the catalogue, and the name of the category they are
  filed under, were read in the language of the page being written instead of the
  language of the back office: editing the English version of a page on a French
  site put "Page blocks" in the middle of a French panel. The sample text inside
  a block still follows the page, which is the point of it.
- The page listing asked the database for the publication state and the address of
  every page, one row at a time. On a tree of 637 pages over four levels that was
  1334 statements, 375 ms and 1.65 MB of HTML for a screen nobody could read; it
  is now 63 statements, 130 ms and 173 KB, and the count no longer depends on how
  many pages the site holds. A search costs 69 statements whatever it finds.
  `PageListingCostTest` compares the cost of the same read on two trees of
  different sizes, so a per-row query coming back is a red test and not a screen
  that slowly gets worse.
- The edit form of a page asked for the publication state of every page on the
  site to build its **Parent** list, and threw all of them away. It no longer
  asks.
- A page with no title in the language being edited was listed as `#28`. It shows
  its title in another language, marked as still to be translated.
- A page with no address in the language being edited was listed as `/`, which is
  the address of the home page.
- The **Scheduled** badge was white on cyan, 1.95:1 where AA asks 4.5:1 of text.
- A page hidden from the site was drawn faded, which took its text down to 1.96:1.
  It carries a mark down its left edge instead. The row also said "hidden" twice,
  once as a badge and once as its state.
- The chevron that opens a branch was an 18 by 18 target. WCAG 2.2 asks for 24 by
  24, and a test now measures every control of the screen.

- An emoji can be typed anywhere in a page. Thelia opens its database connection
  with `SET NAMES 'UTF8'`, which MariaDB and MySQL read as three bytes per
  character, and every emoji needs four: the statement was refused with
  `Incorrect string value` whatever the column was declared as, because the
  character set of the connection is settled before the one of the column. The
  editor made this worse than a plain refusal, since the sanitiser turns
  `&#128247;` into the character it stands for, which is how WordPress stores an
  emoji: a draft was accepted and then failed at publication. Characters above
  that plane are now written out on the way to the database and read back on the
  way out, on the Propel save and hydrate hooks, so every path stores them, from
  the draft and the autosave to publication, revisions, duplication, import and a
  visitor filling in a form. A stylesheet gets the escape a stylesheet
  understands. The fix belongs to the module; the connection belongs to the
  framework, and is reported there.
- Binning a page bins every page under it, however deep. The walk stopped after
  twenty levels, so anything below that stayed online, on the address it had,
  under a parent that had just left the tree. Two other walks counted levels the
  same way: the address of a page rebuilt from nothing lost the top of its path,
  and the breadcrumb of a page nested that deep started in the middle of the
  site. All three now stop on the pages they have already seen, which also ends
  the walk on a tree where a page has become its own ancestor.

- Switching the module off and on again no longer renames the pages. The
  addresses were rebuilt from the titles, so every page whose address had been
  edited by hand moved, with no redirection left behind, and the children moved
  with their parent. A site of three hundred pages lost every indexed address it
  had, and an integration test run against the wrong database was enough to do
  it. The addresses are now put back from the stored slugs.
- Restoring a page from the bin puts it back on the address it had, instead of
  one derived from its title.
- The edit screen shows the segment of the address a page owns rather than its
  whole path. Opening a child page and saving it unchanged used to fold the path
  into the segment and move the page to `parent/parent-child`.

- The media screen counts the whole library rather than the images it has loaded.
  The grid stops at two hundred images, so any library larger than that announced
  the size of one page as the size of the library, and the images still to
  describe beyond it appeared nowhere. The count now comes from the database, and
  the screen says how many of them it is showing.

- Publishing a page that has nothing on it is refused, and said so on both
  screens that publish. It used to be accepted: the snapshot was stored empty,
  the back office read "published", and the visitor got a 404.
- The page served on an address that does not exist now names itself. On an
  address that used to hold a page, the breadcrumb, its `BreadcrumbList` and the
  title kept describing the page that was asked for instead of the one shown.
- Moving a page in the tree now takes the addresses of its descendants with it.
  Only the page itself was re-addressed, so every page under it kept announcing
  the path of a parent it no longer had, at any depth and in every language, with
  no redirection from the old address and none towards the new one. Reorganising a
  tree is what the page list is for, so the defect was reachable from the first
  afternoon on a site. Each descendant keeps the slug it was given and each former
  address stays as a 301, inside the transaction of the save.
- `thelia_cms:publish --all` no longer publishes the seeded legal pages. They are
  created as drafts holding instructions on purpose, and a run of the command put
  seven of them online on a real site, sample text and all, in the sitemap
  included. Publishing a draft that still holds that text is now refused wherever
  it comes from, the back-office button included, and `--dry-run` reports the
  refusals instead of counting the pages it would then turn down.
- An address whose first segment merely begins with the letters of the back office
  gets the 404 page of the site. `/administration-des-ventes` was compared as a
  prefix against `/admin`, so it was taken for the back office and served the bare
  404 of the theme. The same comparison in the back-office sidebar is read by
  segment too.
- The "Text section" block of the webpage preset starts on a level 2 heading.
  Dropped under the title of a page, its level 1 competed with it, and the check
  run at publication reported a problem the author had not caused. The level is
  still changeable from the Tag setting of the heading.

## [1.0.0-alpha.1] - 2026-08-07

First tagged version. The database schema has been stable since 0.6.0, and this
release closes the last known defects found by reading the code and by scanning
the published pages.

### Added

- Page tree in the back office: hierarchy, ordering, visibility, duplication,
  a bin with restore, and "set as home page".
- Hierarchical rewritten URLs per language, with a 301 kept on rename, a
  denylist of reserved prefixes, and a byte limit matching the column.
- Front-office rendering through `cmspage.html.twig` from the active theme, with
  a fallback shipped by the module, both emitting the `cmspage.*` theme hooks.
- Visual page builder on `/admin/cms/pages/{id}/builder`: one canvas per
  language, autosave every 30 seconds, a guard against leaving unsaved work,
  revisions snapshotted at publication, and signed 72-hour preview links for
  clients without a back-office account.
- Publication pipeline: server-side HTML and CSS sanitizer, responsive
  `<picture>` rewriting, plain-text extraction into a FULLTEXT index, and a
  heading-structure check (`heading_check_mode`, `warn` or `block`).
- Menus, called by code from a theme (`main` and `footer` seeded): entries
  pointing at a CMS page, a content, a folder, a web address or a label alone,
  nested up to three levels, reordered with buttons or by dragging. An entry
  whose target is missing, offline or unpublished in the language being read is
  left out of the menu and reported in the back office.
- `cms_menu(code)` and `cms_page_alternates()` for themes, returning data rather
  than markup. Menus are cached per code, language and host, and the cache is
  dropped when a menu or a page it points at changes.
- Language switching that keeps the visitor on the page they were reading, and
  `hreflang` alternates limited to the languages a page is published in.
- Settings screen: what the site is, the page shown when an address does not
  exist, and maintenance.
- Showcase mode: 404 on the cart and the checkout, CMS first in the back-office
  menu, and an "Editor" profile seeded with the content permissions and none of
  the shop.
- Maintenance mode: 503 with `Retry-After`, an IP allow list, a bypass for the
  signed-in administrator, and a maintenance page editable as a CMS page.
- The page shown when an address does not exist can be a CMS page, served with
  its 404 status.
- Media library: grid, multiple upload, search by name or tag, per-language
  alternative text (mandatory unless the image is decorative) and caption,
  dimensions, weight and format, file replacement, and the pages using an image.
- Six ACL resources, declared to the container and seeded into
  `resource`/`profile_resource` so a profile can actually be granted them.
- SEO metadata per language, exposed to SEOne when it is installed: title,
  description, `WebPage` microdata, breadcrumb and `hreflang` alternates.
- Page changes recorded in the core admin log, so the existing System logs
  screen answers who published or unpublished what.
- Four unpublished legal pages seeded as placeholders in every active language.
- Activation guards: URL rewriting and FULLTEXT support are required, and
  deactivation removes the rewritten URLs the module owns.
- Block catalogue: ten blocks to start a page from (hero, text and image, call
  to action, quote, testimonials, key figures, logos, gallery, questions and
  answers, section), described on the server so their sample text follows the
  language of the page. A module or a bundle adds blocks of its own by
  implementing one interface.
- Reusable blocks: content written once in the same builder and placed on any
  number of pages, which all update when it is published. A block still used by
  a page cannot be deleted.
- Live content rendered on every visit rather than stored in the page: latest
  news, a menu, a reusable block. Each one caches its own fragment, so a block
  that changes often does not force the page out of the cache.
- Click-to-load embeds for video, maps and social posts: nothing is requested
  from the platform until the visitor presses the button, and the button is a
  link to the platform when JavaScript is off.
- Editor messages shown as notices over the canvas: they dismiss themselves,
  can be closed by hand, and an error waits until it is.
- A guide for writing a block, with two commented examples.
- Forms built in the back office: nine kinds of field (single line, email,
  several lines, drop-down list, tick box, one choice among several, phone,
  date and an agreement to be contacted), each with its label, help text and
  answers written per language. A field left untranslated is kept out of the
  form in that language and reported in the back office.
- Per form: who the answers are emailed to, a legal notice with a link to the
  privacy policy page, the message shown after sending, an optional copy sent
  back to the person who wrote, whether answers are stored, and how long.
- The `cms-form` block places a form on any page, so adding a field never means
  republishing the pages the form appears on.
- Three checks before a message is accepted, none of which asks anything of the
  visitor: a field only a robot fills in, a signed record of when the form was
  served, and a cap on how many messages one sender may get through.
- The answers screen: search by email address, export as CSV or JSON, and delete
  one answer, which is what answering a request to see or to erase personal data
  comes down to. A cell a spreadsheet would run as a formula is defused on the
  way out.
- Answers age out on their own after the number of days their form states,
  either from `thelia_cms:forms:purge` or along with `maintenance:purge`.
- Showcase dashboard block on the back-office home page: messages received over
  the last thirty days, pages online and pages still drafts, how much of the
  site is written in each language, the five pages changed last, and whether
  anything is measuring the site. Added in showcase mode only.
- `Cache-Tag` and `Surrogate-Key` on every rendered page, and explicit
  invalidation when a page is published, unpublished, binned or restored, when a
  menu is saved, when a reusable block used by pages changes, and when the site
  settings change. A project plugs its CDN in through `CachePurgerInterface`.
  A page is marked `public` only for a plain GET from a visitor with no session
  whose answer sets no cookie; `http_cache_ttl` says for how long.
- The published pages are added to the sitemap of the theme through the
  `sitemap.urls` theme hook, with `lastmod` set to the publication date and an
  `xhtml:link` per language. Pages in the bin, drafts, pages outside their
  publication window and pages marked `noindex` are left out.
- Front-office search on `/recherche` and `/search`, over the text extracted at
  publication. Pages in the bin, drafts, pages awaiting their date and pages
  marked `noindex` stay out of the results, the query is stripped of the MySQL
  boolean operators, and the page answers `X-Robots-Tag: noindex, follow`. A
  theme overrides it with `cms-search.html.twig`; a site running TntSearch gets
  a `CmsPageIndex` over the same rows.
- The maintenance page shipped by the module is now written in the language the
  visitor is reading the site in.
- Scripts and measurement screen: third-party snippets, each with a place in the
  document, the consent vendor it waits for, a note and an on/off switch. A
  snippet naming a vendor is written into the page inert and started only once
  the visitor has agreed to that vendor.
- Axeptio and Google Consent Mode v2: the defaults are emitted denied at the top
  of the head before any tag runs, the SDK loads next, and what the visitor
  agrees to both updates the Google signals and releases the snippets of that
  vendor.
- A bin that empties itself: pages deleted more than `trash_retention_days` ago
  (30 by default) go for good with their content, their revisions, their search
  entries and their addresses, either from `thelia_cms:pages:purge-trash` or
  along with `maintenance:purge`. The bin screen shows when each page is due to
  go.
- A sent form is reported to the data layer as `generate_lead`, carrying the
  code of the form and nothing else, and only when the person agreed to be
  contacted.
- `thelia_cms:export` and `thelia_cms:import`: the page tree, the content of
  every language, the menus, the forms, the reusable blocks and the settings, as
  one versioned JSON file. Images travel as file names and are matched against
  the media library of the importing site. Submissions, third-party snippets and
  revisions are left out. An import leaves alone what is already there unless
  `--replace` is passed, applies the settings only with `--with-settings`, and
  runs in a single transaction.
- Templates screen: keep a page aside as a starting point, and start a page from
  one. A page made from a template is a hidden draft and opens straight in the
  editor. A template holds the export document of its page, so it can be handed
  to another site as a file.
- `cms_site_icon()` and the `/site-icon` route: a theme shows the icon uploaded
  in Configuration > Store, which Thelia otherwise keeps out of reach of the
  front office, and falls back on its own file when none is configured.
- A page for putting a shared cache in front of the site (`docs/shared-cache.md`):
  the VCL that drops the session cookie on public addresses, without which no
  page is ever cacheable, the equivalent for Fastly and Cloudflare, and a purger
  to copy into a project.
- A printable guide of the module in French, written for whoever runs the site
  rather than for a developer, with a shot of every screen
  (`docs/guide/thelia-cms-guide-webmaster.pdf`).
- Unit test suite runnable without a database or an installed shop, an
  integration suite over the export and import round trip, the search index, the
  sitemap, the dashboard and the breadcrumb, and an axe-core scan of the pages a
  visitor reads.

### Fixed

- The TntSearch index was never registered: the guard around it named a class in
  the module's own namespace, so it was false on every site.
- The breadcrumb of a page filed under a draft one offered a link to that page,
  which answers 404, both to the reader and in the `BreadcrumbList`.

[1.0.0-alpha.1]: https://github.com/thelia-modules/TheliaCMS/releases/tag/1.0.0-alpha.1
