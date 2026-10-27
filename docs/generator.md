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
- Four settings, in the **Generator** card of CMS > Settings, each with a
  default: `generator_model` (`claude-opus-5-5`), `generator_effort` (`high`;
  `low`, `medium`, `xhigh` and `max` exist), `generator_max_tokens` (16000),
  `generator_web_fetch` (on: read the web pages a brief cites, below).
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

### Web pages the brief cites

A brief, a change or a section asked again may cite web pages by their
address (`http://…` or `https://…`): "take our story from
https://example.com/about, shorter". The user message then lists them and asks
the model to read each before it writes, and the request offers `web_fetch`,
the tool the Claude API runs itself (`web_fetch_20260209`): the page is read
from the infrastructure of Anthropic, never from the server of the shop. Five
pages at most per request, the ones cited and the links followed from them,
each cut at 20,000 tokens. What a page says counts as given by the brief; a
page that cannot be read (private, behind a login, an error) is left out.
The address of a picture (a path ending in `.jpg`, `.png`, `.webp`, `.gif`,
`.svg` or `.avif`) is not a page: it is not offered for reading, and goes as
it is into an image prop.

A brief that cites no address sends the request it always sent, without the
tool, and so does every brief once **Read the web pages a brief cites** is
turned off: the addresses then stay in the brief as text. The tool has to be
allowed for the organisation of the API key (Claude Console, privacy
settings); the pages read are billed as input tokens. A translation cites
none: its source is the document of the other language.

## Skills

A skill is a piece of instructions the generator reads before writing: how a
page is structured here, how text is written, what accessibility asks, what
search engines read, how a page is translated, how it stays within the design
system. The module ships seven under `skills/<code>/SKILL.md` (`catalogue`,
`page-structure`, `copywriting`, `accessibility`, `seo`, `translation`,
`design-system`), a
theme may ship its own under `skills/` with the same layout, and a theme skill
with the code of a module skill replaces it.

The files are a seed: the first read sows them into `cms_skill`, and the
site edits them there, under **CMS > Skills** (the settings right). A skill
can be rewritten, switched off, moved up or down from the arrows of the list,
and put back to the shipped text;
a shipped file that changes updates the stored text unless the site rewrote
it. The generator reads the table, never the files.

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

## The screen

CMS > Pages > a page > **Edit content** (`/admin/cms/pages/{id}/content`).
The brief on the left, the draft on the right as the theme shows it, through
the signed preview link of the draft.

- The catalogue the generator writes from is the theme's first: the
  components the theme opens in `config/theliacms.yaml` (with the sections of
  the module the theme puts away under `hidden`), then the sections and the
  partials of the module (`docs/creating-a-component.md`).
- **See every section the generator can use**: a link, shown in debug with
  the Flexy theme, to the page the module hangs in the toolkit
  (`/toolkit/cms-all-sections`): every section with its sample, live content
  named and described. A brief that names a section asks for it.
- **Write it from a brief**: what the page should say, its tone and audience.
  The answer replaces the whole draft. The form holds the brief the page was
  last written from in this language (kept in the ledger, column `brief`),
  so a page is written again from its own brief, corrected.
- **The sections of the draft**: each one numbered and quoted by its title,
  with a form to rewrite that section alone; the others are kept as they
  are, ids included. A section is also removed by hand, after confirmation.
  The number of a section is shown on it in the preview when the pointer is
  on its row, and the row is lit when the pointer is on the section; the
  label of a row scrolls the preview to the section. The renderer puts
  `data-cms-section="<id>"` on the first element of every section for that
  (`Content\DocumentRenderer::SECTION_ATTRIBUTE`, kept by the sanitizer).
- **Ask for a change**: a change on the whole draft, same rule.
- **Written from another language**: when the page holds nothing in the
  language on screen and a draft in another, the stage offers to write this
  language from it (`GenerationRequest::translation`): the same sections,
  components, ids, images and references, the texts written for the new
  reader rather than word for word. One language is picked by default, in
  the order of the shop; a select lets the editor choose when there are
  several. The brief form stays at hand for a page written on its own.
- **History**: the calls of the ledger for this page and language, with their
  outcome, turns, duration and cost, and what each one left out of the brief.
- **Publish**: the usual pipeline, with the heading check.

Every action is a form that posts and comes back with a message, so the
screen works without its script. With it, the forms post in the background
and the progress of the call is shown as it runs: the answer is a stream of
server-sent events (`start`, `turn`, `delta`, `correction`, `done`, `error`),
written by `Generation\Admin\GenerationStream` as the generator reports each
turn, and every few hundred characters of the answer, to its
`GenerationObserverInterface`: the generator reads the answer of the API as a
stream (`Generation\StreamedMessage` puts the message back together). When
the call ends, the script fetches the screen at `?done=<id>` and puts it in
place of the one on show, so the address bar, the scroll and the open panels
stay as they were; without the script, the browser is sent to that address.
Either way the outcome is read from that row of the ledger: a stream can
leave no flash message behind, and the ledger says more. A draft that leaves
out part of the brief — an image the media library does not hold and the
brief gives no address of, a product the search does not find — comes back
as a warning listing what is missing, in the words of the model: the answer
carries it in an optional `left_out` list next to the sections, and the
editor learns it there rather than by reading the page.

Each section also opens a form to **edit it by hand**, read from the schema
of its component (`Generation\Admin\SectionFormBuilder`): a field per prop,
a textarea the Tiptap module upgrades for a `html` prop when the module is
there (`thelia/tiptap-module`, suggested, not required), rows added and
removed for a list prop, a picker on the source of a partial for a
reference. An image is chosen in the media library, with a preview, or
replaced by a file that joins the library on the spot (the media right to
create is needed), or given as the `https://` address of a picture kept
elsewhere, stored as it is and served from there without resizing. What comes back goes
through the same validator as a generated document, is stored through the
writer, and is written to the ledger as a `manual` row with no cost, so the
next request starts from the corrected text. A word corrected by hand costs
no generation, and takes the right to change pages, not the generate right.
With the script, the form posts in the background and the screen it comes
back with is put in place, the panel still open, as at the end of a
generation.

Opening the screen takes the page right; asking the generator takes
`admin.cms.generate`; publishing takes the right to change pages.

## The ledger

Every call writes a row of `cms_page_generation`, whatever its outcome: the
page and the language, the kind of request (brief, revision, section), the
prompt, the model, the tokens read and written, the cache hits, the turns, an
estimate of the cost in US dollars from a price list shipped with the module,
the duration, the stop reason and, for a refusal, its category, the
document produced, and the web pages the call read (`web_pages`): each
address, the error code of a page that could not be read, and the tokens of
the text read. Those tokens are counted after the call by the token counting
endpoint of the API, which is not billed, and priced in the history of the
screen as one uncached reading: the cost of the call already includes them,
and a page the model reads again at a later turn is billed again, at the cache
price after the first. A count that fails leaves the page uncounted. What the
answer said the page leaves out is kept as a JSON list of sentences
(`left_out`), at most ten of 300 characters. `GenerationLog::history()` is what a page screen reads;
`spentSince()` what a budget alert reads.

## Components made in the back office

`Generation/Component/ComponentGenerator.php` makes a component of the site
from a request (see [creating-a-component.md](creating-a-component.md#a-component-made-in-the-back-office)).
Same client, model, effort and price list as the pages; no tool. The system
prompt (`ComponentPrompt`), cached, gives the JSON form of the props, the rules
of the sandbox and of the stylesheet, the colour tokens read from
`assets/blocks.css`, the accessibility skill and an example; the user message
gives the shop, the components the catalogue already has, the request and,
for a change, the component as it is. The answer is constrained to a schema
of seven strings (`code`, `label`, `description`, `props` and `sample` as JSON
written as a string, `template`, `css`). A component the validator refuses,
a new one whose code is taken, a change that renames its component or that
breaks the pages using it goes back with every problem listed, twice at most.

Its calls are in the ledger with the pages, under `target_type` `component`:
`target_id` is the row of the component, 0 for one not kept yet, and the
call a new component came from is filed under it once it is kept. A
candidate is such a row, its `document` holding the component; nothing else
stores it until it is kept.

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
rubric: `docs/evaluation/`.
