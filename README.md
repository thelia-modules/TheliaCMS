# TheliaCMS

A CMS for Thelia 3: a tree of pages, edited in a visual page builder and
published as plain HTML and CSS. A published page ships no builder JavaScript.

> **Alpha.** Pages, the builder and the media library work; menus, forms and the
> front-office search are not there yet. See [Scope](#scope).

## Compatibility

- Thelia 3.0
- PHP 8.3+
- MySQL 5.6+ or MariaDB 10.0.5+, because the front-office search uses a native
  FULLTEXT index
- URL rewriting switched on: every CMS page is served through a rewritten URL,
  and the module refuses to activate without it

## Installation

The module is not on Packagist yet. Until it is, add this repository to the
project's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/thelia-modules/TheliaCMS" }
    ]
}
```

Then:

```bash
composer require thelia/cms-module
php Thelia module:activate TheliaCMS
php Thelia cache:clear
```

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
| `admin.cms.media` | the media library |
| `admin.cms.custom-code` | free HTML in the editor, and `<iframe>` in published content |
| `admin.cms.menu`, `admin.cms.form`, `admin.cms.settings` | reserved for the screens still to come |

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

When [SEOne][seone] is installed, CMS pages describe themselves to it: title,
description, `WebPage` microdata, and a breadcrumb built from the page tree.
Their `hreflang` alternates come from the languages a page is published in.
Without SEOne the module runs unchanged.

## Scope

Working today: the page tree with its bin and duplication, the visual builder
with drafts, revisions, autosave and shared previews, the publication pipeline
(sanitizer, responsive images, search indexing, heading check), the media
library, hierarchical URLs with 301s on rename, the ACL, and the activity log.

Not yet: menus, forms, the front-office search screen, the showcase mode,
reusable blocks and dynamic partials.

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
