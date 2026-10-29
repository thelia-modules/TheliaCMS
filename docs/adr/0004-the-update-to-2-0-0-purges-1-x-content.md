# 4. The update to 2.0.0 purges the content of 1.x

Date: 2026-10-20. Status: accepted.

## Context

The HTML a 1.x site stores has no document to be read back from (ADR 1), and
a conversion of free markup into sections would be a guess.

## Decision

The migration to 2.0.0 drops the content of every page and block (draft,
published, project data, stylesheet) and their revisions, search entries and
saved page templates. Pages keep their tree, addresses, SEO, tags and types,
and go back to draft.

## Consequences

- A site in production loses its pages at the update unless it exported
  them first: `thelia_cms:export` keeps the HTML, and the procedure of an
  update must run it. The CHANGELOG says so in its first entry.
- A version 1 export still imports everything but the contents.

## Amendment of 2026-10-20

The update also drops the tables of the blocks (`cms_block`,
`cms_block_i18n`, `cms_block_content`, `cms_item_block`): blocks are no
longer part of the module. An export taken before the update keeps them; a
2.0.0 module imports the rest of that file and says it left the blocks out.

It drops `cms_page_template` as well: saved page templates are no longer
part of the module, rather than emptied as the decision above says.

## Amendment of 2026-10-29

The update no longer destroys anything. The content of the pages and of the
revisions is still emptied for the new editor, but the columns of the former
editor are copied first to `cms_legacy_page_content` and
`cms_legacy_page_revision`, and the five tables above are renamed
`cms_legacy_block`, `cms_legacy_block_i18n`, `cms_legacy_block_content`,
`cms_legacy_item_block` and `cms_legacy_page_template` instead of dropped.
The module never reads them. The reason: a site that updates in production
lost its blocks and its saved templates with no way back, and 2.x has no
model that holds them (a block is markup of the former editor, which no
document can be read back from), so a rename costs nothing and keeps the data
for whoever needs it. The copies of the pages and of the revisions follow the
same rule: they are made only where something was written. A table with no row has nothing to keep and is dropped,
which is what a fresh install meets: it replays the updates of 1.x, and they
create the tables of the blocks empty. The replay of the update leaves the
legacy tables alone.
