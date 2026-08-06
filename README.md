# TheliaCMS

All-in-one CMS for Thelia 3: page tree, visual page builder, menus, forms and media.

## Tests

The unit tests need neither a database nor an installed shop, only the
autoloader of the project the module sits in:

```bash
vendor/bin/phpunit -c local/modules/TheliaCMS
```

They cover what is worth being sure of without a shop around it: the sanitizer
against a corpus of hostile HTML and CSS, the content normalizer, the responsive
image rewriting, the heading check, the signing of preview links and the slug
rules. Anything that needs real pages — publishing, activation, the front-office
routes — belongs to the integration suite of the shop.

## Page builder assets

The editor is bundled by the module itself and served with `module_asset()` on
the page builder screen only — a published page ships no builder JavaScript.
The compiled files are committed, so installing the module needs no Node.js.

Rebuild them after changing anything under `assets/src/`:

```bash
cd assets
npm install
npm run build     # or npm run watch
```

The build reads the editor sources from `openstudio/page-builder-bundle`, which
it locates in the Composer vendor directory of the surrounding project, so run
`composer install` there first.

While developing, Thelia only republishes an asset to `public/assets` when the
`process_assets` configuration value is on; with it off, the previous build
keeps being served.
