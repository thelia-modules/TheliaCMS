# Sections

A page of the CMS is a **document**: an ordered list of sections, each a
component of the catalogue with the values of its props. The document is what
is stored and edited; the HTML next to it in the database is its rendering,
produced when the page is saved and again when it is published.

```json
{
  "version": 1,
  "sections": [
    {"id": "hero", "component": "Cms:Hero", "props": {"title": "Des studios photo à louer", "cta_label": "Réserver", "cta_url": "/contact"}},
    {"id": "faq", "component": "Cms:Questions", "props": {"title": "Questions fréquentes", "items": [{"question": "…", "answer": "<p>…</p>"}]}}
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
| Sections of the module | `Cms:Hero`, `Cms:MediaText`, `Cms:Cta`, `Cms:Quote`, `Cms:Testimonials`, `Cms:Figures`, `Cms:Logos`, `Cms:Gallery`, `Cms:Questions`, `Cms:Section` | The module, one class each under `Content/Section/`, rendered by `templates/front/sections/<slug>.html.twig`. |
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

```php
final readonly class OpeningHoursSection extends AbstractTemplateSection
{
    public const string NAME = 'MyModule:OpeningHours';

    public function name(): string { return self::NAME; }
    public function label(): string { return $this->trans('Opening hours'); }

    public function description(): string
    {
        return $this->trans('The opening hours of the shop, one line per day. Use it on a contact or access page.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::text('title', $this->trans('Heading'), required: true, max: 120),
            Prop::items('days', $this->trans('Days'), [
                Prop::text('day', $this->trans('Day'), required: true, max: 20),
                Prop::text('hours', $this->trans('Hours'), required: true, max: 40),
            ], min: 1, max: 7),
        ]);
    }

    public function sample(): array
    {
        return ['title' => 'Opening hours', 'days' => [['day' => 'Monday', 'hours' => '9:00 – 18:00']]];
    }

    protected function slug(): string { return 'opening-hours'; }
}
```

`AbstractTemplateSection` renders `cms/sections/<slug>.html.twig` from the
active theme when it ships one, and `@YourModule/…` otherwise: override
`render()` when the fallback lives elsewhere. The template receives `props`
as the schema normalised them, `images` (each image prop resolved to
`{id, url, width, height}` or null, the items of a list under the index of the
item), `uid` (stable for these props, for the ids your `aria-labelledby`
points at), `section` and `locale`.

`description()` is read by a generator to pick the section: say what it is
for and when to use it. `sample()` is what the toolkit renders and the example
a generator is shown: realistic, complete, in the language of the back office.

## Taking a section over in a theme

Ship `cms/sections/<slug>.html.twig` in the theme: `cms/sections/hero.html.twig`
replaces the hero of the module, with the same variables. Keep the `cms-*`
class names the block stylesheet of the module knows, or restyle them in the
theme: the stylesheet sits in a cascade layer, so a rule of the theme wins
whatever the order the two are loaded in.

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
sample props when none are given: `{{ cms_section('Cms:Hero', {title: '…'}) }}`.
An unknown section renders nothing. It is also how the stories of the toolkit
of the Flexy theme (`/toolkit`, in debug) show every section of the module.
