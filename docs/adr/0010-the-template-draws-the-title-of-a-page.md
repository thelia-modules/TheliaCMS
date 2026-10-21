# 10. The template draws the title of a page

Date: 2026-10-21. Status: accepted.

## Context

A page of the CMS is rendered with the first template found among
`cmspage-{type}` and `cmspage`, of the theme then of the module. Flexy ships
neither, so its pages fell on the fallback of the module: the sections of
the page and nothing around them. A content of the shop (`content.html.twig`)
opens with the heading band of the theme — title, summary, breadcrumb — and
lays its text out in the containers of the theme; a CMS page did not, and
the fields of a page type (ADR 9) were shown nowhere.

The generator wrote the level 1 heading itself, in a heading band section or
a free text: a template drawing the title would have made two.

## Decision

**The title of a page is its level 1 heading, and the template draws it.**
Every template of a CMS page draws the title, and the summary when there is
one, above the sections; the sections start at level 2. The skills tell the
generator so (no `<h1>`, no heading band to open a page), the heading check
counts the title as the level 1 a content follows and reports a level 1
heading in the content, and the legal pages are seeded without one.

**The module ships the layout of Flexy.** `templates/frontOffice/flexy/
cmspage.html.twig` lays a page out like a content of the shop: the heading
band of the theme (`Layouts:Subheader:Item`) with the title, the summary and
the breadcrumb, the fields of the page type, then the sections. Thelia finds
it as a template of the theme, so a page without a `cmspage-{type}` of its
own gets it, and a theme or a type that ships its own template still wins.
The fallback of the module, for any other theme, draws the title as an
`<h1>` and the fields as a list.

## Consequences

- A page whose document holds an `<h1>` shows two titles until it is
  written again; 2.0.0 is not released, so no site holds such a page yet.
- A theme taking a page over with its own `cmspage.html.twig` or
  `cmspage-{type}.html.twig` draws the title as the level 1 heading.
- The types screen says such a page is displayed with the usual template of
  the theme: for Flexy, that template comes with the module.
