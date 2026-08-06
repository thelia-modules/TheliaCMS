# TheliaCMS

A CMS for Thelia 3: a tree of pages, edited in a visual page builder and
published as plain HTML and CSS. A published page ships no builder JavaScript.

> **Alpha.** Pages, the builder, the media library and menus work; forms and the
> front-office search are not there yet. See [Scope](#scope).

## Compatibility

- Thelia 3.0
- PHP 8.3+
- MySQL 5.6+ or MariaDB 10.0.5+, because the front-office search uses a native
  FULLTEXT index
- URL rewriting switched on: every CMS page is served through a rewritten URL,
  and the module refuses to activate without it

## Installation

No version is tagged yet, so install the development branch:

```bash
composer require thelia/cms-module:dev-main
php Thelia module:activate TheliaCMS
php Thelia cache:clear
```

Composer pulls in the page builder bundle and TheliaLibrary, and registers the
bundle through Symfony Flex.

Activation creates the tables, seeds the ACL resources and generates the page
URLs. It also seeds four unpublished legal pages (legal notice, privacy policy,
cookies, accessibility statement) as placeholders in every active language.
Nothing is served on the front until you publish them.

Deactivating the module removes the rewritten URLs it owns, so the site answers
404 rather than 500; reactivating regenerates them from the pages.

## Editing a page

**Site > Pages** lists the tree. A page carries a title, a slug, a parent, a
layout, a publication window and its SEO metadata, all per language. Content is
edited on its own full-screen route, `/admin/cms/pages/{id}/builder`:

- drafts autosave every 30 seconds, and leaving with unsaved work asks first;
- **Preview the draft** gives a signed link, valid 72 hours, that a client
  without a back-office account can open. It is `noindex` and never cached;
- publishing runs the content through a server-side sanitizer, rewrites the
  images and extracts the text for the search index;
- the heading structure is checked at publication (see `heading_check_mode`).

Each language holds its own layout and its own text: the French and English
versions of a page are two independent canvases.

## Media

**Site > Media** stores images in [TheliaLibrary][library]. Alternative text is
mandatory in every language unless the image is marked decorative, which
publishes it with an empty `alt`. An empty attribute on its own cannot say
whether an image was described or simply forgotten, so the choice is recorded.

Each image shows its dimensions, weight, format and the pages using it. One
still in use cannot be deleted.

Uploads accept JPEG, PNG and WebP. SVG is refused: it is a document that can
carry script.

At publication every image becomes a `<picture>` with a WebP alternative, a
`srcset` bounded by the file's real width, explicit `width` and `height`, and
lazy loading on everything but the first image.

## Menus

Menus live under **Site > Menus** and a theme calls them by code. `main` and
`footer` exist from the first activation.

An entry points at a CMS page, a content, a folder, a web address, or at nothing
at all. An entry with no target is a label on its own, which is how a group
heading is made. Its own label is optional: left empty, the title of the target
is shown, in the language being read.

The tree goes three levels deep. Entries are reordered with the move buttons or
by dragging a row, and nested either by picking a parent in the form or by
dropping a row onto the right-hand third of another one. Dragging is a shortcut.
It cannot be done with a keyboard, so it is never the only way to reorder a
menu.

An entry whose target has been deleted, taken offline, or left unpublished in
the language being read is **left out of the menu** in that language, and listed
in the back office with the reason. A heading that still has usable children
stays as a heading, rather than let its children move up a level.

## Configuration

Settings live in `module_config` and are read through
`TheliaCMS::getConfigValue()`.

Most of them are edited under **Site > Settings**.

| Value | Default | Description |
|---|---|---|
| `home_page_id` | none | Page served on `/`. Set from the page list; its own slug then 301s to `/`. |
| `site_mode` | `commerce` | `vitrine` closes the shop paths and puts Site first in the back-office menu. |
| `404_page_id` | none | CMS page served when an address does not exist, with a 404 status. |
| `maintenance_active` | `0` | `1` closes the site with a 503. |
| `maintenance_allowlist` | empty | IP addresses and CIDR ranges that keep seeing the site while it is closed. |
| `maintenance_page_id` | none | CMS page shown while the site is closed. |
| `cache_ttl` | `3600` | Seconds a resolved menu is cached for. It is also dropped on every change that affects it. |
| `heading_check_mode` | `warn` | `warn` reports heading problems and publishes anyway; `block` refuses to publish. |
| `builder_stylesheet` | none | Public path of the stylesheet the editor canvas loads. Defaults to the asset mapper's `styles/app.css`. |
| `builder_palette` | none | JSON array of hex colours offered in the editor, e.g. `["#111827","#ffffff"]`. Defaults to a contrast-checked set. |

## Showcase mode, maintenance and the 404

A **showcase site** answers 404 on `/cart`, `/order` and `/checkout`, and moves
Site to the top of the back-office menu. Nothing else changes, and switching back
undoes it: the shop is one save away.

Saving showcase mode also creates the **Editor** profile: pages, menus, media,
forms and news, and none of the shop, none of these settings and no free HTML.
Assign it under **Configuration > Administrators**; its permissions are yours to
change afterwards, and it is never touched again.

**Maintenance** answers 503 with `Retry-After`, which asks search engines to come
back rather than to drop the page. A 200 saying "back soon" is what gets indexed
in place of a site, and a 404 is what gets it removed. Addresses that do not
resolve answer 503 as well, since the check runs before routing. Three ways
through: the back office, the IP addresses on the allow list, and an
administrator already signed in. The page shown can be a CMS page; if it is not
published in the visitor's language, a plain page from the module is served
instead, because the theme is part of what may be under repair.

The **page shown when an address does not exist** is a CMS page like any other,
served with the 404 status. Answering 200 would have search engines index it
under every wrong address ever linked to the site.

## Permissions

Six resources, seeded on activation with no access granted. Open them per
profile under **Configuration > Administrators**.

| Resource | Covers |
|---|---|
| `admin.cms.page` | the page tree, the builder, publication |
| `admin.cms.menu` | the menus and their entries |
| `admin.cms.media` | the media library |
| `admin.cms.custom-code` | free HTML in the editor, and `<iframe>` in published content |
| `admin.cms.settings` | the settings screen: showcase mode, maintenance, the 404 page |
| `admin.cms.form` | reserved for the screens still to come |

Every route under `/admin/cms` is guarded by the resource of its section, so a
route added later cannot ship unprotected by omission.

## Theme integration

The module renders a page with `cmspage.html.twig` from the active theme when
the theme provides one, and falls back to its own otherwise. It therefore works
on any Twig theme exposing a `base.html.twig`, and a theme can take the layout
over without touching the module.

Both versions emit the same hooks, which are the extension points for other
modules. Each receives the page as `page`.

| Hook | Type | Rendered |
|---|---|---|
| `cmspage.top` | front | before the content |
| `cmspage.content.before` | front | inside the article, before the content |
| `cmspage.content.after` | front | inside the article, after the content |
| `cmspage.bottom` | front | after the content |

### Twig functions

| Function | Returns |
|---|---|
| `cms_menu(code, locale)` | the tree of a menu. Each entry has `label`, `url` (null for a heading), `blank`, `children`, `active`, `in_trail`. The locale defaults to the one being served. |
| `cms_page_alternates()` | the page being served, in each language it exists in: `locale`, `code`, `title`, `url`, `current` |

They return data rather than markup, because navigation markup belongs to the
theme. A menu of any depth takes a dozen lines:

```twig
{% macro menu(entries) %}
    <ul>
        {% for entry in entries %}
            <li>
                {% if entry.url %}
                    <a href="{{ entry.url }}"{% if entry.blank %} target="_blank" rel="noopener"{% endif %}>{{ entry.label }}</a>
                {% else %}
                    <span>{{ entry.label }}</span>
                {% endif %}
                {% if entry.children is not empty %}{{ _self.menu(entry.children) }}{% endif %}
            </li>
        {% endfor %}
    </ul>
{% endmacro %}

{{ _self.menu(cms_menu('main')) }}
```

Menus are cached per code, language and host, and the cache is dropped whenever a
menu is saved, or a page it points at is renamed, published, unpublished or
binned.

`cms_page_alternates()` is what a language switcher should be built on: it
answers with the current page in each language, absolute and on the right domain
when the shop runs one domain per language, and it leaves out the languages the
page is not published in. It works beyond CMS pages: on a product or a
category it follows the rewritten URL of that object in the other language, and
elsewhere it carries the current path over. A switcher built on a `?lang=`
parameter alone sends the visitor back to the home page, losing the page they
were reading; the same addresses feed the `hreflang` tags, so the two cannot
drift apart.

When [SEOne][seone] is installed, CMS pages describe themselves to it: title,
description, `WebPage` microdata, and a breadcrumb built from the page tree.
Their `hreflang` alternates come from the languages a page is published in.
Without SEOne the module runs unchanged.

## Scope

Working today: the page tree with its bin and duplication, the visual builder
with drafts, revisions, autosave and shared previews, the publication pipeline
(sanitizer, responsive images, search indexing, heading check), the media
library, menus, hierarchical URLs with 301s on rename, showcase mode, maintenance,
the editable 404, the ACL, and the activity log.

Not yet: forms, the front-office search screen, reusable blocks and dynamic
partials.

## Tests

The unit tests need neither a database nor an installed shop, only the
autoloader of the project the module sits in:

```bash
vendor/bin/phpunit -c local/modules/TheliaCMS
```

They cover the sanitizer against a corpus of hostile HTML and CSS, the content
normalizer, the responsive image rewriting, the heading check, the signing of
preview links and the slug rules. Anything needing real pages belongs to the
integration suite of the shop: publishing, activation, the front-office routes.

## Page builder assets

The editor is bundled by the module and served with `module_asset()` on the
builder screen only. The compiled files are committed, so installing the module
needs no Node.js.

Rebuild them after changing anything under `assets/src/`:

```bash
cd assets
npm install
npm run build     # or npm run watch
```

The build reads the editor sources from `openstudio/page-builder-bundle` in the
Composer vendor directory of the surrounding project, so run `composer install`
there first.

While developing, Thelia only republishes an asset to `public/assets` when the
`process_assets` configuration value is on; with it off, the previous build
keeps being served.

## Licence

LGPL-3.0-or-later. See [LICENSE](LICENSE).

[library]: https://github.com/thelia-modules/TheliaLibrary
[seone]: https://github.com/thelia-modules/SEOne
