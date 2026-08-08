# Changelog

All notable changes to this module are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this module adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

### Fixed

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
