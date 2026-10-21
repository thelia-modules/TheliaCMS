# Follow-up: the image section and what a draft leaves out

Status: four small changes planned, not started. The plan for searching
pictures on the web is separate (`docs/plans/web-search.md`).

## Where it stands

On `feat/ai-page-generation`:

- `626f5ad` adds the `cms:image` section (`Content/ImageSection.php`, the
  address of a picture read by `Content/ImageSource.php`, styles in
  `assets/blocks.css`).
- `ed5c27c` makes the generator say what a draft leaves out of the brief: the
  optional `left_out` list of the answer (`Content/DocumentSchema.php`,
  `Generation/PageGenerator.php::leftOutFrom()`), the column
  `cms_page_generation.left_out`, the warning of the outcome and the history
  (`Generation/Admin/ContentScreen.php`, `content/edit.html.twig`), and
  `thelia_cms:generate`.

Checked against a real generation in the browser at 1440 px: a change asking
for a picture given by its `https` address and for a picture the site does
not have added a `cms:image` section with its caption, and the outcome came
back as a warning naming the missing picture, kept in the history.

## What that check showed

1. **A false entry in `left_out`.** The brief carried the address of the
   picture, so `web_fetch` was offered (`PromptBuilder::readableAddresses()`
   takes every `http(s)` address of the brief), and the model tried to read
   the picture as a page, then reported it could not open it, although the
   picture was on the page.
2. **The warning goes away by itself.** `_toasts.html.twig` keeps only a
   `danger` notice until it is closed; a warning listing what is missing is
   gone within seconds, and only the history keeps it.
3. **A photo is large by default.** `width` defaults to `column`: a landscape
   photo came out 1200 × 900 pixels.
4. **An empty text of the image.** The model left `alt` empty because the
   caption describes the picture, as the help of the prop allows; a screen
   reader then reads an image with no text before its caption.

## Steps

1. **Picture addresses are not pages.** In `Brief::addresses()` or
   `PromptBuilder::readableAddresses()`, leave out an address whose path ends
   with an image extension (jpg, jpeg, png, webp, gif, svg, avif): it is
   offered as is to an image prop, not read. Test: a brief citing only a
   picture is not offered `web_fetch`; a brief citing a page and a picture is
   offered it for the page alone.
2. **A warning with details stays.** In `_toasts.html.twig`, a notice that has
   details is permanent too (`data-cms-toast-permanent-value`), whatever its
   type; the controller is in
   `templates/backOffice/default-twig/assets/content-editor.js`. Test: the
   outcome of a draft with `left_out` renders a permanent notice. Then
   `ddev exec php Thelia thelia:cache:clear` and check in the browser.
3. **Default width.** Decide between `column` and `narrow` for `cms:image`
   (to settle with the product owner); if `narrow`, change the default in
   `ImageSection::schema()` and `sample()`, the unit test and
   `docs/sections.md`.
4. **Text of the image.** Make `alt` describe the picture even with a caption
   (help of the prop in `ImageSection`, rule in `skills/accessibility/SKILL.md`),
   unless the picture is decorative.

## Not checked yet

- The published page: the publication pipeline (`Content/Pipeline/ImageRewriter.php`)
  on a `cms:image` section, a library image wrapped in `<picture>` with its
  `srcset`.
- The section on a phone width.
- A `cms:image` holding an image of the media library rather than an address
  (the local library is empty).

## Decisions taken

- What the generator could not provide goes in `left_out`, written by the
  model, rather than a check on words of the brief: it works in every
  language and for every kind of missing item.
- `left_out` is never a reason to send a document back for correction: a
  malformed list is read as no list.
- An image section with no image draws nothing; its sample stays valid.

## Resuming

Read, in order: this file, `docs/generator.md` (sections "The screen" and
"The ledger"), `Content/ImageSection.php`, `Generation/Brief.php`,
`Generation/PromptBuilder.php`, `templates/backOffice/default-twig/_toasts.html.twig`.
Run the unit and integration suites as the project instructions say, follow
the steps in order with their tests, then check in the browser with a real
generation on a draft page, a picture address in the change asked.
