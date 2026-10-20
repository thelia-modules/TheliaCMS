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
