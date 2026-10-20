# Sections

A page of the CMS is a **document**: an ordered list of sections, each a
component of the catalogue with the values of its props. The document is what
is stored and edited; the HTML next to it in the database is its rendering,
produced when the page is saved and again when it is published.

```json
{
  "version": 1,
  "sections": [
    {"id": "head", "component": "Layouts:Subheader:Base", "props": {"title": "Des studios photo à louer"}},
    {"id": "intro", "component": "cms:richtext", "props": {"html": "<p>Trois plateaux, à l’heure ou à la journée.</p>"}},
    {"id": "contact", "component": "Partial:cms-form", "props": {"form": 1}}
  ]
}
```

A section names a component by the name the catalogue knows it under, carries
an id of its own (lowercase letters, digits, hyphens and underscores) so a
later change can replace it alone, and holds props the component accepts. A
document whose sections name no component of the catalogue, or carry props a
component refuses, is rejected on the way in, every problem listed at once
(`TheliaCMS\Content\InvalidDocumentException`).

## The catalogue

`TheliaCMS\Content\SectionCatalog` holds every component a document may use:

| Family | Names | Where they come from |
|---|---|---|
| Free text | `cms:richtext` | The module. A piece of markup as is, filtered on the rendered page like everything that goes online. |
| Live content | `Partial:<name>` | Every partial of the site (`PartialDefinitionInterface`), the ones of the module and the ones other modules register. The section renders the placeholder the partial renderer substitutes when the page is served. |
| Components of the theme | As the theme names them, `Molecules:Reinsurance:Base` | The components the active theme opens to pages in `config/theliacms.yaml` (below). |

A component of another module joins the catalogue by implementing
`TheliaCMS\Content\SectionDefinitionInterface`; implementing it is what
registers it. Several at once, decided at runtime, go through
`SectionProviderInterface`.

## Props

A component describes its props with `TheliaCMS\Content\Prop`, and the schema
(`PropSchema`) is what checks the values, describes them to whoever writes a
document, and becomes the JSON schema a generator is constrained with.

| Type | Holds | Checked |
|---|---|---|
| `text` | a line of plain text, printed escaped | length |
| `html` | markup, printed as is, filtered by the writer on the rendered page | that it is a string |
| `integer` | a whole number | range |
| `boolean` | true or false | |
| `choice` | one of a closed list | membership |
| `image` | the id of an image of the media library, `null` or `0` for none yet | a positive id |
| `url` | a path of the site, an absolute http(s), mailto or tel address | nothing else, `javascript:` first of all |
| `items` | a list of objects, each shaped by item props | count, and each item |
| `reference` | the id of an item of the site: a page, a menu, a form, a product | a positive id |
| `json` | a list or an object the component does not describe further | that it is one |

Unknown props are dropped, missing ones get their default, wrong ones are
refused.

## Writing a section of your own

The first way is a component of the theme, opened in one line of
`config/theliacms.yaml` (below). A module that has to compute what it shows
writes a partial (`PartialDefinitionInterface`), or a class implementing
`SectionDefinitionInterface`:

```php
final readonly class OpeningHoursSection implements SectionDefinitionInterface
{
    public const string NAME = 'MyModule:OpeningHours';

    public function __construct(private Environment $twig) {}

    public function name(): string { return self::NAME; }
    public function label(): string { return 'Opening hours'; }

    public function description(): string
    {
        return 'The opening hours of the shop, one line per day. Use it on a contact or access page.';
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::text('title', 'Heading', required: true, max: 120),
            Prop::items('days', 'Days', [
                Prop::text('day', 'Day', required: true, max: 20),
                Prop::text('hours', 'Hours', required: true, max: 40),
            ], min: 1, max: 7),
        ]);
    }

    public function sample(): array
    {
        return ['title' => 'Opening hours', 'days' => [['day' => 'Monday', 'hours' => '9:00 – 18:00']]];
    }

    public function normalizeProps(array $props): array
    {
        return $this->schema()->normalize($props);
    }

    public function render(array $props, string $locale): string
    {
        return $this->twig->render('@MyModule/opening-hours.html.twig', ['props' => $props, 'locale' => $locale]);
    }
}
```

`description()` is read by a generator to pick the section: say what it is
for and when to use it. `sample()` is what the toolkit renders and the example
a generator is shown: realistic, complete, in the language of the back office.

## Opening the components of the theme

A theme built on Twig components (Flexy, or a child of it) opens some of them
to the pages of the CMS in `config/theliacms.yaml`:

```yaml
sections:
    components:
        - Molecules:Reinsurance:Base
        - Organisms:ArticleCard:Base
```

Nothing is opened by default: a cart line or a checkout step makes no sense on
a page, and the theme is the one that knows which components do. A child theme
inherits the list of its parent unless it declares its own.

The props of a component are read off its template: the `{# @prop name type
description #}` comments at the top give the type and the meaning, the
`{% props name = default, … %}` tag the defaults. A prop declared without a
default and not documented as nullable is required. `camelCase` in the
template is `snake_case` in a document, and goes back to the component under
its own name. A `{# @description … #}` comment, when the template has one, is
what a generator reads about the component. Slots (`{% block %}`) are not
supported: a component whose content goes through a block cannot be filled
from a document.

## From a template

`cms_section(name, props)` renders a section anywhere in a theme, with its
sample props when none are given: `{{ cms_section('cms:richtext', {html: '…'}) }}`.
An unknown section renders nothing. It is also how the page of the toolkit of
the Flexy theme (`/toolkit/cms-all-sections`, in debug) shows every section.
