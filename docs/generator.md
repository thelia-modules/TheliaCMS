# The generator

A page is written from a brief: an editor says what the page is for, in their
own words, and the generator answers with a document (see
[sections.md](sections.md)) the site validates, renders and stores as the
draft of the page. Publishing stays a decision of the editor, through the
usual pipeline.

## What it needs

- A key for the Claude API in the environment of the site, `ANTHROPIC_API_KEY`.
  Never in a setting: a setting travels with an export, a key must not. Without
  it the generator says so (`GenerationUnavailableException`) and nothing is
  called.
- Three settings, under CMS > Settings, each with a default: `generator_model`
  (`claude-opus-5-5`), `generator_effort` (`high`; `low`, `medium`, `xhigh`
  and `max` exist), `generator_max_tokens` (16000).
- The right `admin.cms.generate`, given to the editor profile the module
  seeds: asking the generator to write costs money.

## What the model is told

The system prompt is the same for every call of the site, written in a stable
order so the API caches it for an hour: the role, the **skills** (below), then
the **catalogue** — every section with its description, its props and one
example. The user message carries what changes: the name of the shop and what
it says about itself, the language of the page, the currency, the title and
type of the page, the summary the editor typed, then the brief; for a change
on an existing page, the document the page holds and the change asked.

The answer is constrained by a structured output to the envelope of a
document: a list of sections, each naming a component of the catalogue
(an enum) and carrying its props as a JSON object written as a string. The
grammar the API compiles a schema into cannot hold the whole catalogue — it
caps optional properties, union types and its own size — so the props are
read back and checked by the validator against the full schema of each
component, and a document it refuses is sent back with every problem listed,
twice at most; a third refusal is an error the editor sees.

While it writes, the model may ask the site:

| Tool | Answers |
|---|---|
| `search_products`, `search_categories` | the products and categories online, by name, reference or barcode |
| `search_pages` | the pages online in the language of the page, with their address, for internal links |
| `search_media` | the images of the media library, by title or tag: the only source of an image id |
| `read_section` | the full sheet of one component |
| `read_skill` | a skill listed as available on demand |

A tool is a class implementing `TheliaCMS\Generation\Tool\GeneratorToolInterface`;
implementing it is what offers it.

## Skills

A skill is a piece of instructions the generator reads before writing: how a
page is structured here, how text is written, what accessibility asks, what
search engines read. The module ships five under `skills/<code>/SKILL.md`
(`catalogue`, `page-structure`, `copywriting`, `accessibility`, `seo`), a
theme may ship its own under `skills/` with the same layout, and a theme skill
with the code of a module skill replaces it.

```markdown
---
name: copywriting
description: How to write the text of a page.
always: true
---

# Writing the text
…
```

`always: true` puts the whole skill in every prompt; the others are named with
their description and read through `read_skill` when the page calls for them.

The files are the seed, the table `cms_skill` is what the site reads: the first
read sows the files, and a site edits a skill under CMS > Skills — its text,
whether it is on, its order — with "restore the shipped text" to come back.
A file that changes afterwards updates the shipped text kept next to the
site's, and the text itself only where the site never rewrote it.

## The ledger

Every call writes a row of `cms_page_generation`, whatever its outcome: the
page and the language, the kind of request (brief, revision, section), the
prompt, the model, the tokens read and written, the cache hits, the turns, an
estimate of the cost in US dollars from a price list shipped with the module,
the duration, the stop reason and, for a refusal, its category, and the
document produced. `GenerationLog::history()` is what a page screen reads;
`spentSince()` what a budget alert reads.

## From the command line

```bash
php Thelia thelia_cms:generate --page 12 --locale fr_FR --brief "Une page qui présente nos services…"
php Thelia thelia_cms:generate --page 12 --locale fr_FR --brief brief.md --revise
php Thelia thelia_cms:generate --page 12 --locale fr_FR --brief brief.md --dry-run
```

The brief is a text or the path of a file. `--revise` reads it as a change on
the document the page holds; `--dry-run` prints the document and saves
nothing. The draft is saved through the writer, like a save from the back
office; `thelia_cms:publish` puts it online.

## Tests

Nothing in the test suites reaches the API: the generator takes its client
from `ClaudeClientProviderInterface`, and the integration tests hand it a
transport that answers from recorded responses (`Tests/Integration/Generation/FakeClaudeTransport.php`).
The quality of what the real model writes is measured by hand, against a
rubric: `docs/chantier/ai-pages/eval/`.
