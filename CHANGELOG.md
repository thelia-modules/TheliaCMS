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
- Unit test suite runnable without a database or an installed shop.

[Unreleased]: https://github.com/thelia-modules/TheliaCMS/commits/main
