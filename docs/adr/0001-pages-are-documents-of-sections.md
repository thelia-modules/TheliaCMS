# 1. A page is a document of sections

Date: 2026-10-20. Status: accepted.

## Context

Until 1.5.0 the content of a page was what a visual editor (GrapesJS,
through `openstudio/page-builder-bundle`) exported: free HTML, a stylesheet
and the project of the editor. Writing pages with a language model needs
content the server can constrain, validate and regenerate piece by piece,
and markup the theme already styles: the front theme compiles Tailwind from
an explicit list of sources, so a class the model would invent has no CSS.

## Decision

The content of a page or a block is a JSON document: an ordered list of
sections, each naming a component of the catalogue and giving the values of
its props. The document is validated against the schema of each component
(`Content\DocumentValidator`) and rendered to HTML by Twig when it is saved
and again when it is published (`Content\DocumentRenderer`). The model never
writes markup or classes, only props; a `html` prop holds plain markup the
sanitizer filters at publication.

## Consequences

- A page can be written from a brief, changed by asking, rewritten one
  section at a time, or edited by hand through a form read from the schema.
- The markup of a page always comes from a template of the module or of the
  theme, so a component whose template changes is republished the way it
  looks today.
- The content of a 1.x site has no document to be read back from: the
  update to 2.0.0 purges it (ADR 4).
