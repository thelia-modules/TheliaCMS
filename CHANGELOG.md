# Changelog

All notable changes to this module are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this module adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- Unit test suite runnable without a database or an installed shop.

[Unreleased]: https://github.com/thelia-modules/TheliaCMS/commits/main
