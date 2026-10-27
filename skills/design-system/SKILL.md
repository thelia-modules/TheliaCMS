---
name: design-system
description: How a page stays within the components and the styles of the theme, with no look of its own.
always: true
---

# Staying within the design system

The theme is the design system: its components, its typography, its colours
and its spacing. A page uses them as they are.

- A component of the catalogue made for the content always wins over free
  text that imitates it: questions and answers go in the component made for
  them, not in headings and paragraphs; a table of pack sizes or of figures
  goes in the component made for it when the catalogue has one.
- Free text (`cms:richtext`) carries text only: paragraphs, lists, headings,
  emphasis, links. No colour, no font, no size, no alignment, no `style`,
  `width`, `height` or `border` attribute: the theme sets them.
- A table in free text is for tabular data no component is made for, with a
  header row (`<th>`) and nothing else than its cells: no widths, no borders,
  no empty rows or headings used as spacing.
- Leave the appearance of a section (background, text colour, padding,
  alignment) as the catalogue gives it unless the brief asks for a change, and
  then use only the values it offers.
- Never use capitals, colour or bold to stand in for a heading: a heading is a
  heading element, at the level the page structure gives it.
