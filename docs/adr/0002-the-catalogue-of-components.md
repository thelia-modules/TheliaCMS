# 2. The catalogue: sections of the module, partials, components of the theme

Date: 2026-10-20. Status: accepted.

## Context

The model needs a closed list of components with typed props, and a theme
needs to add its own without forking the module. The front theme (Flexy)
ships UX Twig components but none for editorial pages.

## Decision

The catalogue (`Content\SectionCatalog`) is made of three families:

- the ten former page blocks, rewritten as sections of the module
  (`Cms:Hero` … `Cms:Section`), each a class with typed props and a template
  under `templates/front/sections/` a theme takes over with
  `cms/sections/<slug>.html.twig`; plus `cms:richtext`, free markup;
- the dynamic partials of the module (`Partial:<name>`): a form, a menu, a
  block, the latest contents, a product or a category;
- the components of the theme the theme lists in `config/theliacms.yaml`
  (`sections.components`), whose props are read from their `{% props %}`.

Sections are rendered by the template resolver of the theme rather than as
anonymous UX components of the module: a module cannot put a component where
the theme looks for anonymous ones, and a registered class component always
wins over a file of the theme.

## Consequences

- A theme adds a component with one line of configuration, a module with one
  class (`docs/creating-a-component.md`).
- The prompt carries the whole catalogue (about 17 000 tokens for twenty
  components), cached by the API for an hour; a theme that opens many
  components raises the cost of the first call of each hour.

## Amendment of 2026-10-20

The components of the theme come first. The reader of `@prop` comments
knows the records of the site (`product`, `category`, `image`, lists of
them) and the parameters the PHP class of a component takes, so a component
that reads its own data — a row of products from ids — is used as it is. A
theme puts the sections of the module it replaces away under
`sections.hidden`, and the `catalogue` skill tells the model to prefer a
component of the theme. The sections of the module remain for what the
theme has no component for, and for a theme that declares nothing.
