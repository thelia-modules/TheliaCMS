# 3. Written from a brief, changed by asking, corrected by hand

Date: 2026-10-20. Status: accepted.

## Context

An editor writes a page in their own words; a generated page is never right
the first time; a comma should not cost a generation.

## Decision

The generator (`Generation\PageGenerator`) sends a brief to the Claude API
and gets a document back, constrained to the envelope of the schema and
validated server side. Three kinds of request share one screen: a brief
(the whole draft replaced), a change on the whole draft, and one section
written again while the others are kept, ids included. Every section also
opens a form read from the schema of its component to edit it by hand; the
Tiptap module of the back office upgrades the `html` props when it is there.
A reusable block or the block of a catalogue item is written on the same
screen, told to the model for what it is and forbidden to hold a block.

Every call is written to a ledger (`cms_page_generation`) with its tokens,
an estimate of its cost and its outcome, and a change by hand is written
there too, so the history reads in order and the next request starts from
the corrected text.

## Consequences

- Asking the generator is a right of its own (`admin.cms.generate`): it
  costs money. Editing by hand takes the right to change pages.
- No budget ceiling exists yet; `GenerationLog::spentSince()` is where an
  alert would read.
- Adding, removing or reordering sections by hand is not offered: the brief
  and the change request cover it until an editor asks otherwise.
