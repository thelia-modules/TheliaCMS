# Creating a block

Thelia CMS has two kinds of block, and picking the right one takes one question:
**does the content change after the page is published?**

| | Static block | Dynamic block |
|---|---|---|
| Where the content lives | in the page, as markup | on the server, rendered on every visit |
| Editors change it | in the canvas, like any text | through the settings of the block |
| Good for | a hero, a call to action, a gallery | a news list, a menu, an embed, anything shared |
| You write | a `CatalogBlockProviderInterface` | a `PartialDefinitionInterface` |

Both are plain PHP classes in a module or a bundle. Neither needs a line of
configuration: implementing the interface is what registers them.

The two examples below are shipped and commented in `docs/examples/`.

## A static block

A static block is a piece of markup dropped into the page. From that moment on
it belongs to the page: the editor rewrites its text, moves it around and
deletes it like anything else, and the page keeps working if your module is
later removed.

```php
final readonly class OpeningHoursBlocks implements CatalogBlockProviderInterface
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function blocks(string $locale): array
    {
        return [new CatalogBlock(
            id: 'shop-opening-hours',
            label: $this->translator->trans('Opening hours', [], 'mymodule', $locale),
            content: '<section class="shop-hours" aria-labelledby="shop-hours-title">…</section>',
            category: $this->translator->trans('My shop', [], 'mymodule', $locale),
        )];
    }
}
```

The `$locale` you are handed is the language of the **page being written**, not
of the back office. Translate the sample text with it, or an editor writing the
German version of a page gets English placeholder text.

### The rules a block has to keep

These are the ones a review will send a block back for, and the ten shipped
blocks all answer to them:

- **A block is a `<section>` named by its own heading**, through
  `aria-labelledby` pointing at the id of that heading. It is what makes the
  block appear under a useful name in the landmark list of a screen reader.
  Ids of a dropped block are made unique by the editor, so write them plainly.
- **Headings go down one level at a time**, and only a hero carries an `h1`. The
  publication check warns about the rest.
- **Anything clickable is a link or a button**, never a `div` with a handler, and
  measures at least 24 by 24 pixels once styled.
- **Nothing depends on dragging or hovering.** The accordion of the catalogue is
  a `<details>`/`<summary>` for that reason: it opens with a keyboard on its own.
  Testimonials are a list rather than a carousel for the same one.
- **No colour, no spacing, no font in the markup.** A block carries class names;
  the theme stylesheet says what they look like. That split is what lets a
  project restyle everything at once, and it is where the contrast ratios were
  checked.
- **No third-party iframe.** The sanitiser removes them at publish time. An
  embed is a dynamic block with a facade (see below).

Your class names are yours, but the theme has to know them: ship the CSS in the
theme, next to `assets/styles/cms-blocks.css`.

## A dynamic block

A dynamic block stores nothing in the page but its name and its settings:

```html
<div data-cms-partial="opening-hours" data-props='{"shop":3}'></div>
```

The markup is produced when a visitor arrives, which is why the news list of a
page written six months ago is still today's news.

```php
final readonly class OpeningHoursPartial implements PartialDefinitionInterface
{
    public function name(): string          { return 'opening-hours'; }
    public function label(): string         { return $this->trans('Opening hours'); }
    public function themeTemplate(): string { return 'cms/partials/opening-hours'; }
    public function fallbackTemplate(): string
    {
        return '@MyModule/front/partials/opening-hours.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::reference('shop', $this->trans('Shop'), source: '/admin/my-module/shops'),
            PartialProp::boolean('today_only', $this->trans('Only show today'), default: false),
        ];
    }

    public function context(array $props, string $locale): array
    {
        return ['hours' => $this->hours->of((int) $props['shop'], $locale)];
    }

    public function cacheTtl(): ?int { return 900; }
}
```

What you get for free, and what it costs you:

- **`props()` is a contract.** Whatever ends up in `data-props` is coerced to the
  types you declared, kept inside the bounds you gave, and anything you did not
  declare is dropped. A template never has to defend itself.
- **`name()` is the only thing a page stores.** Templates are resolved through
  the registry, never from the page, which is why a page cannot name a file.
- **The theme wins.** If the active theme ships `themeTemplate()`, it is
  rendered; otherwise your `fallbackTemplate()` is. Ship the fallback, always:
  it is what makes the block work on a theme that has never heard of it.
- **`cacheTtl()` is a fragment cache**, keyed by the settings, the language and
  the host. Return `null` for something that must be computed every time. If a
  write in your module changes what the block renders, drop the fragments:
  `PartialCache::invalidate('opening-hours')`.
- **The editor previews it** by calling the module, so what an author sees while
  setting the block up is what visitors will get.

### Embedding a third party

Do not put an iframe in a template. Use the facade partial, the way the video,
map and social blocks do:

```twig
{{ include('@TheliaCMSModule/front/partials/_facade.html.twig', {
    kind: 'video',
    embed_url: embed_url,
    page_url: page_url,
    poster_url: poster_url,
    title: title,
    summary: null,
    notice: notice,
    action_label: play_label,
}) }}
```

Until a visitor presses the button, the page requests nothing from the platform:
no iframe, no script, no cookie, no IP address handed over. The button is a link
to the platform, so it works without JavaScript as well.

Two things to get right: build `embed_url` from a fixed list of providers rather
than from an address an editor types, and say in `notice` which company is about
to receive something and what it may set.

## Testing your block

The pieces that do not need a database are worth a unit test, and the module
runs its own suite:

```bash
vendor/bin/phpunit -c local/modules/TheliaCMS
```

Then look at it in a browser. A block is markup an editor drags around: whether
it survives being duplicated, emptied and moved is not something a test tells
you.
