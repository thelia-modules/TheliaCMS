---
name: page-structure
description: The order of sections a page of each kind expects, and the heading structure that lets anyone navigate it.
always: true
---

# Page structure

## Headings

- The title of the page is its one `<h1>`, the main heading: the theme draws
  it above the sections, with the summary of the page. Never write an `<h1>`,
  and never open with a heading band: the first section is what comes under
  the title.
- Section headings are `<h2>`. Inside a section, `<h3>`. Never skip a level:
  `<h2>` then `<h4>` is a hole a screen reader falls into.
- A heading says what the section is about in the words a visitor would
  search for. Not "Our strengths" but "Delivery in 48 hours across France".

## What a page of each kind holds

The orders below list every step a page of each kind may have. A step
the brief gives no material for is left out; a step marked *(asked)* is
written only when the brief asks for it in so many words.

**Landing or service page**: under the title, what and for whom → the one
thing that matters most → proof, reviews or reassurance → questions and
answers that remove the last doubts → what to do next *(asked)*.

**Selection or collection page** (a theme, a season, the products of a
story): under the title, what ties them together in a short text →
the products in one row (the row component of the theme, when the catalogue
has one), two to twelve of them, found with `search_products` → a link to the
category or the shop. Several rows when the brief groups the products, one
heading each, never one card per product.

**About page**: under the title, the sentence that says who you are →
the story or the team → figures → partners or clients when the brief names
them → how to get in touch *(asked)*.

**Legal or informational page** (legal notice, terms, privacy, accessibility,
access): free text in `<h2>` sections, nothing decorative, no
call to action. Keep the text the brief gives you; do not write law.

**Contact or access page**: free text with the address and
the hours, a form if the site has one (`Partial:cms-form` with its id), a map
if the brief gives an address and the catalogue offers one.

**News or article**: free text in `<h2>` sections, dated facts in the order they
matter, one picture when the library has one, no call to action unless the
brief asks.

## Sections nobody asked for

A form, a text inviting the visitor to get in touch, a call to action: each
one only when the brief asks for it — a button, a form, a way to contact,
"invite people to visit". A brief that asks for none of them gets none,
however naturally a page would end on one. When the brief asks for a button
to a page the site does not have, the form of the site may take its place.

## When the brief gives little

A brief of one line, or a brief that asks for things the site does not have
(products the search does not find, images the library does not hold), gives
a short page: say only what the brief said, in as few sections as it takes,
and leave out what you would have to make up. A section that says nothing —
"ask us your question", "discover our world" — is worse than no section.
Say what you left out in `left_out`, one sentence each: the editor reads it
and can add the image or the product, then ask again.

## Length

- Under the title, one or two lines that say what the page is about.
- A section of text: two to four short paragraphs. A paragraph: three
  sentences.
- Three reviews, three figures, three to six questions: enough to
  convince, not enough to tire.
