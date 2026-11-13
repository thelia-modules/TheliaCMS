# 16. A called component reads the stored values of its caller

Date: 2026-11-13. Status: accepted. Amends ADR 15.

## Context

ADR 15 lets a component of the site draw another kept for the site with
`cms_component('<Code>', {key: value})`, the values in their template form:
an image is its address, an `html` prop a `Twig\Markup`. A component of the
theme cannot be drawn that way. It is drawn outside the sandbox from the
values a page stores (an image is an id of the media library), which
`ThemeComponentSection::draw()` turns into what the theme reads: addresses,
the part of the picture the editor kept, known icons, the poster of a video,
the names the theme spells its props with.

The nine trials of round 4 of the bench passed `props.<name>` only, never a
field of a row in a loop.

## Decision

- **A call passes the stored values of its caller.** A value is
  `props.<name>`, given to the called component as the page stores it, or a
  value written in the template (a string, a number, a boolean). `item.<name>`
  and any other expression are refused when the component is saved.
- **The called component shapes them itself**, through its own section: a
  component of the site through `SiteComponentSection::render()`, a component
  of the theme through `ThemeComponentSection::render()`, both after
  `normalizeProps()` of their own schema. A value that schema refuses draws
  nothing for that call and is logged.
- **A component of the theme is called by its catalogue name**
  (`cms_component('Layouts:CrossSelling:Base', {…})`), a component of the
  site by its code as in ADR 15. Only what the catalogue offers is called: a
  component put away or hidden by the theme is refused.
- **What a component of the theme draws is inserted after the filter of the
  caller.** The caller's markup goes through the filter of published content
  as before, with a placeholder where each theme call stands; the markup of
  the theme replaces it afterwards, as for any section of the theme. The
  filter would otherwise take out the Stimulus attributes the theme's
  controllers need.
- One level, 24 calls per rendering, a held component and the breakages of
  a new version stay as ADR 15 has them. A component of the theme has no
  versions in the module: what it no longer takes is reported when the page
  is drawn, as for its own sections.

## Consequences

- One rule for both kinds of called components, and the called component
  validates what it receives with its own schema.
- A component that draws one row per item of a list can no longer call a
  component for each row: it passes the list to a component that loops.
- A component saved under ADR 15 with `item.<name>` in a call stops being
  valid at its next save. 2.27.0, which brought `cms_component`, was
  published the day this was decided; no component of the test host calls
  another (`cms_component.template`, searched on 2026-11-13).
