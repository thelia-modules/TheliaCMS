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
at all — a label alone, which is how a group heading is made. Its own label is
optional: left empty, the title of the target is shown, in the language being
read.

The tree goes three levels deep. Entries are reordered with the move buttons or
by dragging a row, and nested either by picking a parent in the form or by
dropping a row onto the right-hand third of another one. Dragging is the
shortcut, never the only way: it cannot be done with a keyboard.

An entry whose target has been deleted, taken offline, or left unpublished in
the language being read is **left out of the menu** in that language, and listed
in the back office with the reason. A heading that still has usable children
stays as a heading, rather than let its children move up a level.

## Configuration

Settings live in `module_config` and are read through
`TheliaCMS::getConfigValue()`.

| Value | Default | Description |
|---|---|---|
| `home_page_id` | none | Page served on `/`. Set from the page list; its own slug then 301s to `/`. |
| `heading_check_mode` | `warn` | `warn` reports heading problems and publishes anyway; `block` refuses to publish. |
| `builder_stylesheet` | none | Public path of the stylesheet the editor canvas loads. Defaults to the asset mapper's `styles/app.css`. |
| `builder_palette` | none | JSON array of hex colours offered in the editor, e.g. `["#111827","#ffffff"]`. Defaults to a contrast-checked set. |

## Permissions

Six resources, seeded on activation with no access granted. Open them per
profile under **Configuration > Administrators**.

| Resource | Covers |
|---|---|
| `admin.cms.page` | the page tree, the builder, publication |
| `admin.cms.menu` | the menus and their entries |
| `admin.cms.media` | the media library |
| `admin.cms.custom-code` | free HTML in the editor, and `<iframe>` in published content |
| `admin.cms.form`, `admin.cms.settings` | reserved for the screens still to come |

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
| `cms_menu(code, locale)` | the tree of a menu — each entry has `label`, `url` (null for a heading), `blank`, `children`, `active`, `in_trail`. The locale defaults to the one being served. |

It returns data rather than markup, because navigation markup belongs to the
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

When [SEOne][seone] is installed, CMS pages describe themselves to it: title,
description, `WebPage` microdata, and a breadcrumb built from the page tree.
Their `hreflang` alternates come from the languages a page is published in.
Without SEOne the module runs unchanged.

## Scope

Working today: the page tree with its bin and duplication, the visual builder
with drafts, revisions, autosave and shared previews, the publication pipeline
(sanitizer, responsive images, search indexing, heading check), the media
library, menus, hierarchical URLs with 301s on rename, the ACL, and the activity
log.

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
