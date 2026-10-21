# 9. A page type is a template of pages

Date: 2026-10-21. Status: accepted.

## Context

Sites publish series of pages built the same way: recipes, case studies,
events. An editor wants to start each one from a page that already has the
right sections, a brief that says what such a page is about, and a few
values of its own (number of servings, cooking time) that a list of the
series can show, sort and filter on.

Until now a page type was a code and nothing else (`cms_page_type`): it
picks the `cmspage-{code}` template and survives an export by its code. A
tag groups pages under a free label (`cms_pages_tagged`). The duplication
copies a page with its type and tag, but no brief: the brief of a page is
read back from its last generation (`GenerationLog::latestBrief`). Nothing
held a value specific to a series. The saved page templates of 1.x are
gone (2.0.0): they were copies of a page, with no brief and no field.

## Decision

**The page type becomes the template.** No new notion next to it: pages of
a series are the pages of one type, already grouped by `cms_page.page_type`,
rendered by one template, carried by code through an export.

A type gains:

- a name and a description per language (`cms_page_type_i18n`), the code
  staying the identifier;
- a brief (`brief`, `brief_tone`, `brief_audience`), the shape of
  `Generation\Brief`;
- the definition of its fields (`fields`, JSON read by
  `Page\Field\FieldDefinitions`): an ordered list of `{code, kind, labels:
  {locale: text}, unit, choices, translated, required, min, max}`, `kind`
  among `integer`, `decimal`, `duration` (minutes), `text`, `longtext`,
  `boolean`, `choice`, `date`. Only a text is translated; the code of a
  field never changes once it exists;
- one model page: a page of that type with `cms_page.model = 1`, the demo
  content. It is never published (the writer refuses), so the front
  office, the sitemap, the search and the lists, which read published
  content only, never see it; the page list of the back office hides it and
  the type screen links to it. One model per type, kept by the writer; the
  link is the type code, not an id, so it survives an import. Deleting the
  type deletes its model.

**Field values are JSON on the page.** `cms_page.fields` holds the values
of the fields that are the same in every language,
`cms_page_i18n.translated_fields` those of the translated ones.
`Page\Field\PageFields` reads them through the definition of the type —
a value its field no longer takes reads as none — and writes them checked
against it; a code the type does not define is dropped at the next save,
so changing the type of a page drops the values the new type does not
define. Like the other settings of a page, values are live once saved.
A required field is required to publish, not to save a draft.

**A new page starts from the model.** "Start a page from a template" copies
the model the way the duplication does (sections, field values, in every
language) as a draft with `model = 0`, under the title the editor gives.

**The generator reads the type and fills the fields.** The prompt says the
name and the brief of the type before the brief of the page (tone and
audience of the type stand when the page names none), shows the document
of the model to a page written from a brief, and lists the fields with
their kind and what the page holds. The envelope (ADR 7) gains a required
`fields` property written as a JSON string, like `props`, only when fields
are asked: the grammar stays the same size whatever the definition. A page
written or changed whole is asked for every field, a translation for the
translated ones, one section for none. The values are read like a form of
the back office reads them; a refused or missing required value goes back
to the model with the problems of the document, inside the same two
correction turns.

**Lists read the type.** `cms_pages_of_type(code, {sort, direction,
filter, limit, offset})` answers with `PageCard`s, which gain `fields`;
`PublishedPageRepository::ofType()` answers the same. The pages of the type
are read once and ordered, filtered and cut in PHP: the values are typed by
their definition there, which a `JSON_VALUE` comparison is not, and a
series holds a few hundred pages. A `Partial:cms-page-list` section does it
from the builder (type, heading, order, number), rendered when the page is
served and dropped from the cache when a page goes online, offline or
changes a value. A page template reads its own values as
`cms_page.fields.<code>`, and `cms_page_fields(page)` gives them labelled
and written for a visitor.

The export carries the name, brief and fields of each type described, the
`model` flag and the values of each page; the import describes a type the
site lacks or has as a bare code, and keeps the description of a type the
site has described already, saying so when the two differ.

## Consequences

- One template of rendering per type: two series that look alike but carry
  different fields need two types, and two `cmspage-{code}` files, or the
  fallback `cmspage`.
- No new table besides the i18n of the type; the update to 2.0.0 adds the
  columns, the release is not out.
- Sorting and filtering on a value reads every page of the type. A site
  with thousands of pages in one series would need an indexed table of
  values; the repository is the only reader, so the storage can change
  behind it.
- The words of a choice are shown as they are in every language, like a
  tag.
- A field whose kind changes leaves values its field no longer takes: they
  read as empty, in the back office and in the lists, until written again.
- A shared cache in front of the site keeps a page holding a list until
  its lifetime runs out, as it does for a page holding a form: the module
  drops the fragment, not the pages it sits in.
- Tags stay: a tag cuts across types (a "Christmas" tag on recipes and
  events alike).
