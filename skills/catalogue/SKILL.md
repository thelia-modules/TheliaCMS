---
name: catalogue
description: How to compose a page from the sections of the catalogue, and the rules every section obeys.
always: true
---

# Composing a page from the catalogue

A page is a document: an ordered list of sections. Each section names a
component of the catalogue and gives the values of its props. The catalogue
you may use is listed after these skills, with the props of every component
and one example each. **Use only the components listed.** A section naming
anything else is refused.

## Choosing sections

- Open with one section that carries the main heading of the page: `Cms:Hero`
  with `heading_level` 1, or a `cms:richtext` starting with an `<h1>`. Never
  two of them.
- Three to seven sections make a page. One idea per section. A page that is
  only free text is a page nobody reads.
- Pick the section made for the content: a list of questions goes in
  `Cms:Questions`, not in free text; figures go in `Cms:Figures`; a quotation in
  `Cms:Quote`; pictures in `Cms:Gallery`.
- Close with a `Cms:Cta` when the page asks the visitor to do something.
- Live content (`Partial:*`) shows what the site holds today — a form, the
  latest news, a product — and takes the id of that item. Use the search tools
  to find the id; never guess one.
- Several products go in **one** `Partial:product-strip`: a row of cards, the
  ids from `search_products` in `products`, a heading that says what they
  have in common. Never a `Partial:product-card` per product stacked one
  under the other; `Partial:product-card` is for one product on its own,
  next to the text that talks about it. The same for categories:
  `Partial:category-strip` for several, `Partial:category-card` for one.

## Props

- Fill every required prop. Leave an optional prop out, or empty, rather than
  inventing a value for it.
- `text` props are plain text: no markup, no line breaks.
- `html` props hold markup: paragraphs `<p>`, lists `<ul><li>`, emphasis
  `<strong>`, links `<a href>`, headings from `<h2>` down (`<h3>` inside a
  `Cms:Section`). No `style` attribute, no `<script>`, no `<iframe>`, no
  inline images.
- `image` props take the id of an image found with `search_media`, the
  absolute `https://` address of a picture the brief gives, or `null` when
  neither exists. Never a path, never an id you did not get from the tool,
  never an address you made up.
- `url` props take the `url` of a page found with `search_pages`, a
  `tel:` or `mailto:` address the brief gives, or an absolute `https://`
  address the brief gives. Never a path you did not get from a tool, however
  likely it looks: a button to a page the site does not have is a dead end.
  When no page fits, leave the button out (`cta_label` and `cta_url` empty, no
  `Cms:Cta`).
- Every section has an `id`: short, lowercase, unique in the page, saying
  what the section is (`hero`, `faq`, `figures`).

## Output

Answer with the JSON document only, matching the schema you were given:
`{"version": 1, "sections": [...]}`. The `props` of each section is a JSON
object written as a string, with exactly the keys and the value types the
catalogue gives for that component: a list prop holds a JSON list of objects,
a number prop a number, a choice prop one of its values. No prose before or
after the document.
