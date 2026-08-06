# TheliaCMS

All-in-one CMS for Thelia 3: page tree, visual page builder, menus, forms and media.

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
