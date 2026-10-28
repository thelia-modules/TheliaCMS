# Plan: write a rich-text field from a brief

Status: steps 1 to 4 done (2026-10-28): the settings of the texts
(`GeneratorSettings::textModel()`, `textEffort()`, `textMaxTokens()`, on the
settings screen), `Generation/Text/` (`TextGenerator`, `TextPrompt`,
`TextRequest`, `TextField`, `TextSanitizer`, `TextResult`), the kind
`GenerationRequest::KIND_TEXT` and the target `GenerationTarget::field()` in the
ledger. Tested with the fake transport only, never against the API. Steps 5 and
on to do. The design questions are settled (see "Decisions taken").

Change from step 2 as planned: only the `copywriting` skill is inlined; the
others, written for pages made of sections, are listed for `read_skill`.

## What is asked

Next to the rich-text fields of the back office (the Tiptap editor), outside
the content screen of a CMS page, a button writes the text of the field from
a brief: formatted text only (paragraphs, bold, italic, lists, links,
subheadings), shown first, then put in the field. Nothing is saved: the text
is kept when the editor saves the form, as if typed.

## What is there today

- Tiptap mounts on `textarea.wysiwyg` and on the fields its module names
  (`vendor/thelia/modules/Tiptap/Tiptap.php:112-136`), through the back hook
  `wysiwyg.js` (`Hook/WysiwygHookManager.php:42`). It exposes
  `window.TiptapEditor = {arm, mount}` and no instance, and its toolbar takes
  no extension. Checked in the browser on 2026-10-28: the instance is reachable
  as the `editor` property of `.tiptap-wrapper .tiptap-prose`, with
  `commands.setContent()` and `commands.insertContent()`. Its `onUpdate` writes
  the HTML back to the textarea and fires `input` and `change`.
- The fields in scope, checked on the rendered screens:
  - CMS page settings: `cms_page[chapo]`, `cms_page[description]`
    (`Page/Admin/CmsPageType.php:84-85`, hook at
    `templates/backOffice/default-twig/pages/edit.html.twig:229`, location
    `cms-page-edit-js`).
  - Products, categories, brands, folders, contents:
    `thelia_<type>_modification[chapo|description|postscriptum]`, beside
    `[title]`, `[id]` and `[locale]` in the same form. Hook locations
    `product-edit-js`, `category-edit-js`, `wysiwyg-brand-edit-js`,
    `folder-edit-js`, `content-edit-js` (`templates/backOffice/default-twig/*/edit.html.twig`).
- Generator: Anthropic SDK client from `ANTHROPIC_API_KEY`
  (`Generation/EnvironmentClaudeClientProvider.php`), settings in
  `Generation/GeneratorSettings.php`, the right `CmsResources::GENERATE`, the
  ledger `Generation/GenerationLog.php` (table `cms_page_generation`,
  `target_type`), pricing `Generation/Pricing.php`, skills inlined by
  `Generation/PromptBuilder.php:317-346` (`copywriting` always on), tools in
  `Generation/Tool/`, a strict JSON envelope as the only structured output
  (ADR 0007). The smallest generator is
  `Generation/Component/ComponentGenerator.php`, with
  `Tests/Integration/Generation/Component/ComponentGeneratorTest.php` and the
  fake transport `Tests/Integration/Generation/FakeClaudeTransport.php`.
- Every write under `/admin/cms` takes the back-office token
  (`Security/CmsAdminGuard.php:122-125`).

## Steps

1. Settings: `generator_text_model`, `generator_text_effort`,
   `generator_text_max_tokens` in `GeneratorSettings`, on the settings screen
   (CMS > Settings, generator block), translated. Defaults: the page model,
   effort `low`, 2000 tokens.
2. `Generation/Text/TextRequest` (brief, locale, field kind: summary,
   description, conclusion; object type and id; object title; current HTML of
   the field), `TextPrompt` (role, the always-on skills, the field and the
   object, the current text to rewrite or complete), `TextGenerator`: the loop
   of `PageGenerator` with the search tools only (`search_products`,
   `search_categories`, `search_pages`, `read_skill`; no `search_media`, no
   image), `web_fetch` when the brief cites an address as for pages, a strict
   envelope `{html}`, one correction round when the envelope is invalid.
3. Output filter: the HTML of the answer goes through a sanitizer of text only
   (`p`, `br`, `strong`, `em`, `u`, `s`, `ul`, `ol`, `li`, `a[href]` with the
   schemes of the published content, `h2`-`h4`, `blockquote`). Anything else
   is dropped, its text kept.
4. Ledger: one row of `cms_page_generation` per call, kind `text`, the
   `target_type` of the object (`page`, `product`, `category`, `brand`,
   `folder`, `content`), its id, the locale, the cost.
5. Route `POST /admin/cms/text/generate`: the right `GENERATE`, the token of
   the back office, JSON answer `{html, cost}` or `{error}`; no write but the
   ledger. Errors as the generator says them: no key, refusal, max tokens.
6. Hook: the module listens to `wysiwyg.js` at the five catalogue locations
   and on the CMS page screen, and adds its script with the route, the
   messages and, per screen, the selectors of the fields in scope with their
   kind and the field that gives the title. Only for an admin with the right.
7. Script `assets/rich-text-brief.js`: after Tiptap mounts, a button beside
   the toolbar of each field in scope; a `<dialog>` with the brief, then the
   text shown formatted, with *Replace*, *Insert at the cursor*, *Cancel*;
   the instance written through `.tiptap-prose.editor`. The dialog adopts the
   Tiptap overlays (`adoptOverlays`, see the project instructions).
8. Tests: generator with the fake transport (answer, correction, refusal,
   sanitizing, tools offered), route (right, token, ledger row), hook (the
   script on the five screens and the CMS page, not elsewhere, not without
   the right); the script in a browser on a product and a CMS page.
9. Editor documentation, CHANGELOG, strings in `I18n/`.

## Decisions taken

1. Scope: the CMS page settings and the summary, description and conclusion
   of products, categories, brands, folders and contents. Not the media
   descriptions, the Page and Cheque modules, nor the content screen.
2. The text is shown first, then *Replace*, *Insert at the cursor* or *Cancel*.
3. The generator gets the brief, the current text of the field, the field,
   the title of the object, the language, the always-on skills, and the
   search tools of the catalogue and the pages.
4. A model, an effort and a token limit of their own, on the settings screen.

## Open points

- The `editor` property of the ProseMirror element is not a public API of
  Tiptap: an update of the Tiptap module can remove it. The script checks it
  and leaves the field alone when it is missing; a public accessor is worth
  asking the Tiptap module for.
- Whether the object title is read from the form as typed, or from the
  database: the form, so an unsaved title is used.

## Resuming from a blank context

Read this file, then `Generation/Component/ComponentGenerator.php`,
`Generation/PageGenerator.php`, `Generation/GenerationLog.php`,
`vendor/thelia/modules/Tiptap/Hook/WysiwygHookManager.php`. Start at step 1 on
branch `feat/ai-page-generation` of `modules-dev/TheliaCMS`.
