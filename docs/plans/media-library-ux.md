# Plan: a media library that holds a catalogue

Status: lot 1 done (2026-10-28): `MediaQuery`, `MediaGrid`,
`MediaFilter`, `MediaOrder`, `CmsMediaLibrary::selection()`, `sorted()`,
`tagCounts()`, `MediaCatalog::browse()`; tests in
`Tests/Integration/Media/MediaListScreenTest.php`. Found on the way and
fixed: an image with no row in the language edited was left out of the
count to describe. Lot 2 done the same day: cards six a row at 1440 px and wider, the describe
queue (`admin.cms.media.describe`, `MediaCatalog::toDescribe()`,
`MediaDescriptionType`, `CmsMediaWriter::describe()`), tests in
`Tests/Integration/Media/MediaDescribeScreenTest.php`; an image opened from a
filtered list leads back to it. The decorative answer is a button with the
access key `d` (the browser's modifier), not a bare key.

Lot 3 done the same day: `assets/media-upload.js` (drop, one request per
file, progress, refusals), `MediaUpload` and `MediaUploadRefusal`, the queue
restricted to named images (`images=` in the address of the queue only: leaving it goes back to the whole list), tests in
`Tests/Integration/Media/MediaUploadScreenTest.php`. Found on the way and
fixed: the list stored GIF and BMP, and a file the library could not decode
broke the whole sending. Lots 4 to 6 not started. The import of the catalogue
images it starts from is done: `thelia_cms:media:import-catalogue`
(`Media/CatalogueImageImporter.php`), a copy, run again without duplicates.

## What is asked

Make **CMS > Media** and the image pickers of the pages fit a library of
hundreds of images, now that the catalogue can be copied into it: find an
image, describe what is not described, add images, choose one from a page.

## What is there today

Measured on the local shop on 2026-10-28 with 110 images (the catalogue
copied in), viewport 1862 px:

- **List** (`media/list.html.twig`, `Media/Admin/MediaCatalog.php:40`): one
  grid of cards, 4 a row of 376 × 282 px, so 110 images make a page 12,200 px
  high. Newest first, no other order (`CmsMediaLibrary.php:185`). Capped at
  the 200 newest (`CmsMediaLibrary.php:166`), no pagination: beyond that an
  image is reached by search only, which has the same cap.
- **Search**: title and tag titles, in any language (`CmsMediaLibrary.php:318`).
  Not the alternative text, the caption or the file name. No filter: neither
  "to describe", nor "unused", nor by tag.
- **Card**: thumbnail, title, size, weight, format, a "no alternative text" or
  "decorative" badge, tags, usage. The whole card opens the sheet; no action
  on it. The usage of every card is computed on each display by reading the
  content of every page (`MediaUsageFinder`, `MediaCatalog.php:46`).
- **Upload**: a native `<input type=file multiple>` sent on change
  (`list.html.twig:18-19`): no drag and drop, no progress, no error per file.
  Several files land back on the list with a flash "describe them"; nothing
  takes the editor to them.
- **Sheet** (`media/edit.html.twig`): preview, usage, the form (alternative
  text, decorative, caption, title, tags as free text separated by commas,
  replace the file), delete. The language is switched on the sheet only; the
  sheet does not say in which languages the image is still undescribed.
  Delete goes through a native `confirm()` (`edit.html.twig:120`); refused
  when used, with a message that says "page(s)" while the count includes
  catalogue items (`CmsMediaAdminController.php:160`).
- **Pickers**: two implementations. The image of a page is a grid of radio
  cards (`Page/Admin/PageImageChoices.php:43`), the image prop of a section is
  a native `<select>` of titles (`Generation/Admin/SectionFormBuilder.php:422`).
  Both list the 200 newest, without search. The page picker adds the current
  image when it is older (`PageImageChoices.php:46-52`); **the section picker
  does not**: in a library of more than 200 images, a section pointing at an
  older one shows no current choice.
- **Tests**: deletion guard, tally, upload permission, CSRF, usage, import.
  Nothing covers the list (search, cap, badges), the upload, the sheet form or
  the section picker.

## The plan, in lots

Each lot ships alone, with its tests, its lines in the editor documentation
(both languages) and the CHANGELOG. Days are pilot days; the contingency is
at the end, not in the lots.

### 1. A list that scales (0.75 d)

- Pages of 48 images, the number of the page in the address; the count says
  "N images, page p of q".
- Order: newest, oldest, title, weight.
- Filters, combinable with the search and kept in the address: to describe
  (in the edited language), unused, decorative, a tag (a list of the tags with
  their counts, the tags of the import included: Product, Category, a
  category name).
- Search also in the alternative text and the caption (not the file name:
  the stored one is random, the uploaded one is the first title).
- Usage computed for the page of images shown only, as today, but for 48
  instead of 200.
- Tests: pagination, each filter, search fields, order.

### 2. Denser cards, a queue to describe (1 d)

- Cards of about 200 px, 6 a row at 1440 px; title, badge and usage only,
  size and weight on hover and on the sheet.
- A "Describe" mode, reached from the count "34 to describe": one image at a
  time, large, with its title, its usage and the alternative-text field, then
  the next one. Keyboard: Enter saves and moves on, a key marks it decorative.
  The editor describes 34 images without opening 34 sheets.
- Tests: the queue lists the undescribed images of the language and saves.

### 3. Upload (0.5 d)

- A drop zone on the list and the file field kept as a fallback.
- One request per file, with progress and an error for each refused file
  (type, size), the others stored.
- After the upload, the describe queue opens on the images just added.
- Tests: several files with one refused; the queue holds the new ones.

### 4. The sheet (0.75 d)

- The state of the description in every language at a glance (described,
  missing, decorative), each a link to the sheet in that language.
- Tags as chips with suggestions from the existing tags.
- Replace the file with a preview of the new one before saving.
- Delete through a dialog of the back office instead of `confirm()`; the
  refusal says "pages and catalogue items".
- Tests: the form (required alternative text unless decorative, tags, replace),
  the refusal message.

### 5. One picker (1.5 d)

- A dialog shared by the image of a page, the image props of the sections
  and the content of a catalogue item: search, the filters of lot 1, pages of
  images, upload from the dialog, the current image always shown.
- Replaces the radio grid of the page and the `<select>` of the sections.
- Fixes the section picker that loses a current image older than the 200
  newest.
- The Tiptap overlays rule applies if the dialog is modal over the content
  editor (`content-editor.js`, `adoptOverlays`).
- Tests: a section and a page keep an image older than one page of the
  library; search from the picker; upload from the picker.

### 6. Bulk actions (0.75 d)

- Select images on the list: add or remove a tag, mark decorative, delete
  the unused ones (the used ones are listed and kept).
- Tests: each action, and a used image that is not deleted.

Total of the lots: 5.25 d. Contingency for the lot: 1 d, for the picker shared
by screens written apart and for the browser checks. Hypothesis: no new table
(pagination, filters and order are queries over TheliaLibrary), and the
picker is plain JavaScript like the other assets of the module.

## To decide before starting

- The order of the lots: 1 then 2 give the most for an editor facing a copied
  catalogue; 5 is the largest and fixes a latent defect.
- Whether the describe queue (lot 2) may propose an alternative text written
  by the generator from the image, at a cost per image, or stays manual.
- Folders: the help of the tags says "until it gets folders"
  (`CmsMediaType.php:70`). Tags with filters (lot 1) may make folders
  unnecessary; out of this plan unless asked.

## Out of scope

- Reading the catalogue images in place, without a copy: set aside on
  2026-10-28 for the copy command, simpler, at the price of two sources.
- Cropping and focal point.
- SVG, refused on purpose (`CmsMediaType.php:32-35`).
