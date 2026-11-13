# 15. A component of the site draws another by its code

Date: 2026-11-13. Status: accepted.

## Context

A component of the site is a Twig template run in a sandbox without
`include` (`Content/Site/SiteTemplateRenderer.php`). Asked for "a text next
to a carousel" on a site that has a `Carousel`, the generator writes the
carousel again: two copies that drift apart, and a change to the carousel
that never reaches the new component. The bench of components measures it in
three `compose-*` cases.

Three ways were weighed:

- a function of the sandbox that draws a component of the site by its code;
- a prop type "component" holding a code and its props, chosen by the editor;
- a section that holds other sections in columns, at the level of the page.

The prop type touches every reader of a schema (editor form, translation,
document checker, catalogue sheet, framing of images) and makes props
recursive. The section of sections leaves the generator of components out of
it. The maintainer chose the function.

## Decision

- **`cms_component('<Code>', {key: value})`** draws the kept component of the
  site of that code, in the same sandbox, inside its own
  `cms-site cms-site--<code>` element, with the values given. The values are
  in their template form: the parent passes what its own `props` already are
  (an address for an image, a `Twig\Markup` for an `html` prop).
- **The parent declares what it passes** as props of the usual types. The
  editor form, the translation, the checker and the media usage read the
  parent's schema and its rendered markup as before.
- **One level.** A component that calls another cannot be called. The code
  is a constant string, checked at save from the Twig tree; the keys passed
  must be props of the child; a rendering draws 24 calls at most.
- **A failing child draws nothing** and is logged; the whole output goes
  through the sanitizer of published content once.
- **A cited component is held**: not deleted while cited; a version that
  drops a key a parent passes is listed among its breakages.
- **Only components of the site.** A prototype is offered to nobody; a theme
  component is drawn outside the sandbox from stored props, which the parent
  does not have.

## Consequences

- The generator is told the function and the codes and props of the
  components of the site; a composition request becomes a small component
  that places existing ones.
- The stylesheet of the child is already on every page (one file for the
  site). A rule of the parent can match inside the child: the prompt keeps
  the parent to its own parts until a scoper can stop at the child.
- A new version of a component can break its parents as it breaks pages; the
  same screen lists both.
