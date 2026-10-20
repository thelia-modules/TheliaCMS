# 6. The skills are seeded from files and edited in a table

Date: 2026-10-20. Status: accepted.

## Context

How a page is structured and written on a given site belongs to the site,
not to the code; a theme knows its site better than the module.

## Decision

The instructions the generator reads are skills: files `skills/<code>/SKILL.md`
shipped by the module (five) and by the theme, sown into `cms_skill` at the
first read and edited under CMS > Skills (rewrite, switch off, reorder, put
the shipped text back). A shipped file that changes updates the stored text
unless the site rewrote it. The generator reads the table, never the files.

## Consequences

- The quality of what the model writes is steered without a release; the
  manual evaluation (`docs/evaluation/`) is how a change to a skill is judged.
- A skill the files no longer ship is kept: the site may depend on it.
