# Plan: the address field of a page

Status: steps 1 to 4 done (2026-10-28): `CmsUrlService::propose()` returns an
`AddressProposal` without writing, `remember()` stores the segment without the
uniqueness suffix, `CmsPageWriter::saveDraft()` calls
`CmsUrlService::rebuildDescendants()` on a first address in a locale. The two
defect tests of `Tests/Integration/Page/PageAddressTest.php` were red before
the fix. Steps 5 and on to do. The design questions are settled (see
"Decisions taken").

## What is asked

The address of a CMS page is built from its title in each language, under the
addresses of its parent pages. Two defects make that wrong in a multilingual
site, and the slug field gives the editor no way to get the address back into
the right form without saving. Fix the defects, then replace the field with
one that proposes an address computed by the server, written only when the
form is saved. Then do the same for the "Rewritten URL" field of the core
screens, in thelia/thelia.

## What is there today

- `Page/CmsUrlService.php`: `refresh()` (line 64) slugifies the requested slug
  or the title (`normalizeSegment()`), prefixes the address of the closest
  ancestor (`ancestorPath()`, line 371), makes it unique (`makeUnique()`,
  line 405) and writes it (`setRewrittenUrl()` then `remember()`).
- `rewriting_url.url` is unique across languages (`url_UNIQUE`), so the same
  title in two languages gives `cookies` and `cookies-2`.
- `Page/Admin/CmsPageAdminController.php:345` fills the slug field with the
  stored slug (`slugOf()`); an emptied field reaches the server as `null` and
  the address is derived from the title again.
- `Page/Admin/CmsPageWriter.php:113` and `:149`: the subtree is re-addressed
  only when the page had an address in that language and it changed.
- The back office scripts post with the token of the layout
  (`meta[name="bo-token"]`, `templates/backOffice/default-twig/assets/menu-editor.js:19`).

## Defects, reproduced

Measured on the test database with a throwaway integration test, 2026-10-28.

1. **The uniqueness suffix becomes the slug.** `remember()` (line 246) stores
   the last segment of the unique address: a page titled "Cookies" in two
   languages keeps `cookies-2` as its slug in the second one, shown in the field
   and reused by every rebuild. In the dev database: page 3, `fr_FR`.
2. **A child translated before its parent sits at the root, and stays there.**
   The parent has no address nor title in that language, `ancestorPath()`
   contributes nothing, the child gets `probe-little`. Translating the parent
   afterwards does not re-address it: a first address is not a change of
   address, so `rebuildSubtree()` does not run.
3. Not a defect, a misleading screen: once saved, the field always holds the
   slug, so renaming a page never moves its address. The help ("Leave empty to
   derive it from the title") reads as if it did.

## Steps

1. Tests first, each seen failing on the current code: the same title in two
   languages keeps the requested slug (the address alone carries `-2`); a
   parent translated after its child puts the child under it in that
   language, and only in the languages the child already answers in.
2. `CmsUrlService`: split the computation from the write. A read-only method
   takes the page (or none, for a new one), the locale, the title, the
   requested slug and the parent, and returns the segment, the whole address,
   and whether a suffix was added. `refresh()` calls it, then writes.
3. Defect 1: `remember()` stores the requested segment, not the last segment
   of the unique address.
4. Defect 2: when a page gets its first address in a locale, re-address in that
   locale the descendants that already answer in it (`rebuild()`, never
   `refresh()` with no slug, which undoes edited slugs).
5. Route `POST /admin/cms/pages/address-proposal` in
   `CmsPageAdminController`: CSRF token of the back office, the right of the
   edit screen (`AccessManager::UPDATE`), JSON answer, no write. Reserved
   paths and over-long addresses answer an error the field shows.
6. Form type `Page/Admin/PageAddressType.php` in place of the `TextType` of
   `slug`: the input, a "Regenerate from the title" button, the whole address
   below it with the suffix pointed out. Script
   `templates/backOffice/default-twig/assets/page-address.js`: reads the title
   and the parent of the form, posts, fills the input. Nothing is saved until
   the form is.
7. Renaming (decision 2): when the title no longer matches the slug, the field
   says the address no longer follows the title and puts the button forward.
   Nothing changes without a click.
8. `fixedUrl` keeps its free input: a check that the address is free, no
   regeneration.
9. Strings in `I18n/en_US.php` and `I18n/fr_FR.php`; editor documentation
   (`templates/backOffice/default-twig/documentation/_*.html.twig`, address
   section); CHANGELOG.
10. Browser check at 1440 px: a page in two languages, a child translated
    before its parent, a rename.

## Core screens (second delivery, thelia/thelia)

- Field `url` of `core/lib/Thelia/Form/SeoFieldsTrait.php:35`, on the screens
  of contents, folders, categories, products, brands and videos
  (`templates/backOffice/default-twig/*/edit.html.twig`, `seo_form.url`).
- The form of a core address is `title.html`, accents kept
  (`UrlRewritingTrait::generateRewrittenUrl()`), and that method saves: it
  needs a read-only counterpart before a proposal route can exist.
- Reuse the field and the script of the module, moved to the theme
  `default-twig`; the module then uses the theme's. To settle with the
  maintainers of thelia/thelia before coding.

## Decisions taken

1. Scope: the module first, then a separate pull request on thelia/thelia.
2. A renamed page: the screen proposes, never imposes; a published address
   moves only on a click.
3. The `code` fields (menus, forms, page types, fields) keep their input: they
   are identifiers, some frozen once created.

## Not done

- No estimate.
- Existing slugs carrying a suffix (`cookies-2`) are not cleaned: the fix
  stops new ones. A cleanup would change published addresses.

## Resuming from a blank context

Read this file, then `Page/CmsUrlService.php`, `Page/Admin/CmsPageWriter.php`
(`saveDraft()`), `Tests/Integration/Page/PageAddressTest.php`. Start at step 1
on branch `feat/ai-page-generation` of `modules-dev/TheliaCMS`; run the
integration suite with the command of the project instructions, one suite at
a time.
