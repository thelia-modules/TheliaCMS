# Creating a component

A page is a document: an ordered list of sections, each naming a component of
the catalogue and carrying its props ([sections.md](sections.md)). The
catalogue is built from every class implementing
`TheliaCMS\Content\SectionDefinitionInterface` and from the providers that
bring several at once. There are two ways to add to it: open a Twig component
the theme already has, or write a class in a module.

## A component of the theme

A theme built on Twig components (Flexy, or a child of it) names the ones a
page may use under `sections.components` in its `config/theliacms.yaml`:

```yaml
sections:
    components:
        - Molecules:Reinsurance:Base
        - Organisms:ArticleCard:Base
```

A theme that opens a component of its own for something the module also
does puts the module's section away under `sections.hidden`, so the
generator is never asked to choose between the two:

```yaml
sections:
    components:
        - Layouts:CrossSelling:Base
        - Organisms:VideoPlayer:Base
    hidden:
        - Partial:product-strip
        - Partial:product-card
        - Partial:video-facade
```

Nothing is opened until the theme says so. A name is two or more segments of
letters, digits and underscores joined by `:`, and is looked up as
`@Flexy/<segments>.html.twig` with the segments as directories, so
`Molecules:Reinsurance:Base` is `@Flexy/Molecules/Reinsurance/Base.html.twig`.
A child theme inherits the list of its parent unless it declares its own, and
the nearest declaration wins whole. The file is read at every request, so a
line added while working on the theme shows up at once; a name the theme chain
does not ship is logged and skipped rather than fatal. A list that is not a
list, or a name that is not a component name, refuses the whole file.

The props are read off the template (`Content/Theme/ComponentPropsReader.php`),
from two places:

- the `{% props name = default, other %}` tag gives the names and the
  defaults. Only a literal default is understood: `null`, `true`, `false`, a
  number, a quoted string, and `[` or `{` read as an empty list. An expression
  (a function call, a variable) reads as `null`, and the section leaves such a
  prop to the component when the document leaves it empty;
- one `{# @prop name type description #}` comment per prop gives the type and
  the meaning. `bool` or `boolean` is a boolean, `int` or `integer` an integer,
  `array`, `list`, `iterable`, `object` or `array<…>` a JSON prop, a list of
  quoted alternatives (`'primary'|'secondary'`) a choice, and anything else a
  line of text, 500 characters at most. A type naming `null` makes the prop
  optional.
- a prop may name what the site holds: `image` is a picture of the media
  library (its id, or the `https://` address of a picture kept elsewhere);
  `product`, `category`, `folder`, `form`, `menu` or `block` is one record,
  picked by its id with the same pickers the partials use (the generator
  finds the id with its search tools); `product[]`, `category[]`, or
  `list<folder>`, is several of them, two to twelve, stored as rows of ids in
  the order they were picked and handed to the component as a list of ids,
  the way a template of the theme would pass them.
- a prop documented and not declared in the `{% props %}` tag is one the PHP
  class of the component takes in `mount()` (`productIds` of a product strip,
  for instance): it is read as optional, under the name the comment gives.

A prop declared without a default and not documented as nullable is required.
A component with no `@prop` comment at all still works: every declared prop
is read as text. A `{# @description … #}` comment, when the template has one,
is what the generator reads about the component; without it, the sheet says
it is the component of the theme as used on the rest of the site. The
template spells `camelCase`, a document spells `snake_case` (`iconLeft` is
`icon_left`), and the section hands the value back under the name the
component declares. Slots (`{% block %}`) are not read: a component whose
content goes through a block cannot be filled from a document.

## A component of a module

A class implementing `SectionDefinitionInterface` is a component the moment it
is a service of the container with autoconfiguration on, which is how a
Thelia 3 module registers its services: the interface carries the tag. The
easiest path is `TheliaCMS\Content\Section\AbstractTemplateSection`, which
normalises the props through the schema and renders a template the theme may
take over. The example below is a complete section, modelled on
`Content/Section/FiguresSection.php`:

```php
<?php

declare(strict_types=1);

namespace MyModule\Content\Section;

use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\Section\AbstractTemplateSection;

/**
 * The opening hours of the shop, one line per day.
 */
final readonly class OpeningHoursSection extends AbstractTemplateSection
{
    public const string NAME = 'MyModule:OpeningHours';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->translator->trans('Opening hours', [], 'mymodule');
    }

    public function description(): string
    {
        return $this->translator->trans('The opening hours of the shop, one line per day. Use it on a contact or access page.', [], 'mymodule');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            $this->headingProp($this->translator->trans('Heading', [], 'mymodule')),
            Prop::items('days', $this->translator->trans('Days', [], 'mymodule'), [
                Prop::text('day', $this->translator->trans('Day', [], 'mymodule'), required: true, max: 20),
                Prop::text('hours', $this->translator->trans('Hours', [], 'mymodule'), required: true, max: 40, help: $this->translator->trans('As displayed, for instance "9:00 – 18:00".', [], 'mymodule')),
            ], min: 1, max: 7),
            Prop::boolean('closed_on_holidays', $this->translator->trans('Closed on public holidays', [], 'mymodule'), true),
        ]);
    }

    public function sample(): array
    {
        return [
            'title' => 'Opening hours',
            'days' => [
                ['day' => 'Monday to Friday', 'hours' => '9:00 – 18:00'],
                ['day' => 'Saturday', 'hours' => '9:00 – 12:00'],
            ],
            'closed_on_holidays' => true,
        ];
    }

    public function render(array $props, string $locale): string
    {
        return $this->templates->render(
            'cms/sections/'.$this->slug(),
            '@MyModuleModule/front/sections/'.$this->slug().'.html.twig',
            $this->context($props, $locale),
        );
    }

    protected function slug(): string
    {
        return 'opening-hours';
    }
}
```

What each method is for:

- `name()` is the name a document refers to the component by, stable across
  versions. Prefix it with the module, the way the module prefixes its own
  with `Cms:`. The first definition claiming a name wins, so a name the module
  already uses is ignored rather than replaced.
- `label()` is what a person reads in a list; `description()` is one or two
  sentences on what the component is for and when to use it, which is what the
  generator reads to pick it.
- `schema()` declares the props with the constructors of `Content/Prop.php`:
  `Prop::text` (length), `Prop::html` (markup, filtered on the rendered page),
  `Prop::integer` (range), `Prop::boolean`, `Prop::choice` (a closed list),
  `Prop::image` (an id of the media library), `Prop::url` (a path of the site
  or an absolute http(s), mailto or tel address), `Prop::items` (a list of
  objects, each shaped by its own props), `Prop::reference` (the id of a page,
  a menu, a form, a product) and `Prop::json`. A prop name is lowercase
  letters, digits and underscores. `headingProp()` is the `title` prop every
  section with a heading shares.
- `sample()` shows the component at its best: it is what the toolkit renders
  and the example the generator is shown. It must pass the schema.
- `normalizeProps()` comes from the abstract class: unknown props dropped,
  missing ones given their default, wrong ones refused with every problem
  listed (`InvalidDocumentException`). Override it only for a rule the schema
  cannot say.
- `render()` receives the props already normalised. The abstract class renders
  `cms/sections/<slug>.html.twig` from the active theme when the theme ships
  one and `@TheliaCMSModule/front/sections/<slug>.html.twig` otherwise, so a
  module overrides it, as above, to point the fallback at its own template:
  the Twig namespace of a module is its code followed by `Module`. The
  template receives `props`, `images` (each image prop resolved to
  `{id, url, width, height}` or null, the items of a list under the index of
  the item), `uid` (stable for the same props, for the ids an
  `aria-labelledby` points at), `section` and `locale`.
- `trans()` of the abstract class translates in the domain of TheliaCMS; a
  module uses `$this->translator` with its own domain, as above.

The template, at `templates/front/sections/opening-hours.html.twig` of the
module:

```twig
<section class="cms-opening-hours" aria-labelledby="cms-opening-hours-title-{{ uid }}">
    <h2 class="cms-opening-hours__title" id="cms-opening-hours-title-{{ uid }}">{{ props.title }}</h2>
    <dl class="cms-opening-hours__list">
        {% for item in props.days %}
            <dt>{{ item.day }}</dt>
            <dd>{{ item.hours }}</dd>
        {% endfor %}
    </dl>
</section>
```

A theme takes it over by shipping `cms/sections/opening-hours.html.twig`.
Several components decided at runtime (one per row of a table, say) go
through `SectionProviderInterface` instead, the way the partials and the
components of the theme do.

## What the generator does with it

Every component is written into the system prompt as a sheet
(`Generation/CatalogSheet.php`): its name and label, its description, one line
per prop with its type, its constraints and its help, a `*` on the required
ones, and the sample as a complete JSON section whose id is derived from the
last segment of the name. The sheets are written in the same order at every
call so the prompt stays cacheable. The `read_section` tool hands the same
sheet back when the model asks for one component.

The answer of the model is constrained to the envelope of a document, with the
names of the catalogue as an enum; the props come back as JSON and go through
`DocumentValidator`, which calls `normalizeProps()` of each component. A
document that is refused is sent back to the model with every problem listed,
twice at most. The same validator runs on a section edited by hand and on an
import, so a rule written in the schema holds whoever wrote the document. How
the prompt is built and what the model may read: [generator.md](generator.md).

## Testing it

Without a shop, a unit test exercises the schema and the validator alone, the
way `Tests/Unit/Content/DocumentValidatorTest.php` does with
`Tests/Unit/Content/FakeSection.php`: build a `SectionCatalog` from the
definition, validate a `Document` holding a `Section` with the sample, assert
the normalised props, then assert that a wrong value is refused with the
problem named.

With a shop, an integration test extends `CmsIntegrationTestCase` and asks the
container for the section, as `Tests/Integration/Content/Section/FiguresSectionTest.php`
does: the name is the one of the catalogue, the sample normalises to the
declared props, a list below its minimum and an item without its required
prop are refused, and `render()` on the normalised sample, after
`useAnInstalledFrontTheme()`, contains the class names and the
`aria-labelledby` the template writes. For a component of the theme,
`Tests/Integration/Content/Theme/ThemeComponentSectionTest.php` shows how the
props are read off a template and how the rendering goes through the component
renderer.

```bash
vendor/bin/phpunit -c local/modules/TheliaCMS
vendor/bin/phpunit -c local/modules/TheliaCMS/phpunit.integration.xml.dist --filter OpeningHours
```
