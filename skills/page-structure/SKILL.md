---
name: page-structure
description: The order of sections a page of each kind expects, and the heading structure that lets anyone navigate it.
always: true
---

# Page structure

## Headings

- One `<h1>` per page, and it is the main heading: the title of the hero, or
  the first heading of the free text that opens the page.
- Section headings are `<h2>`. Inside a section, `<h3>`. Never skip a level:
  `<h2>` then `<h4>` is a hole a screen reader falls into.
- A heading says what the section is about in the words a visitor would
  search for. Not "Our strengths" but "Delivery in 48 hours across France".

## What a page of each kind holds

**Landing or service page**: hero (what, for whom, one call to action) →
text and image on the one thing that matters most → figures or testimonials
as proof → questions and answers that remove the last doubts → call to action.

**Selection or collection page** (a theme, a season, the products of a
story): hero that says what ties them together → a short text → the products
in one `Partial:product-strip`, two to twelve of them, found with
`search_products` → call to action to the category or the shop. Several
strips when the brief groups the products, one heading each, never one card
per product.

**About page**: hero with the sentence that says who you are → text and image
on the story or the team → figures → logos of partners or clients when the
brief names them → call to action to get in touch.

**Legal or informational page** (legal notice, terms, privacy, accessibility,
access): free text with an `<h1>` and `<h2>` sections, nothing decorative, no
call to action. Keep the text the brief gives you; do not write law.

**Contact or access page**: a short hero or free text with the address and
the hours, a form if the site has one (`Partial:cms-form` with its id), a map
if the brief gives an address and the catalogue offers one.

**News or article**: free text with an `<h1>`, dated facts in the order they
matter, one picture when the library has one, no call to action unless the
brief asks.

## When the brief gives little

A brief of one line, or a brief that asks for things the site does not have
(products the search does not find, images the library does not hold), gives
a short page: say only what the brief said, in as few sections as it takes,
and leave out what you would have to make up. A section that says nothing —
"ask us your question", "discover our world" — is worse than no section.

## Length

- A hero: a heading of eight words at most, one or two lines under it.
- A section of text: two to four short paragraphs. A paragraph: three
  sentences.
- Three testimonials, three figures, three to six questions: enough to
  convince, not enough to tire.
