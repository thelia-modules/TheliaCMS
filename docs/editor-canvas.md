# The editor canvas of a theme

The canvas of the visual editor is an iframe. It only shows what visitors will
see if it loads the stylesheets the theme loads and carries the classes the
theme puts around the content. Out of the box it loads:

1. the socle of the block catalogue (`/cms/blocks.css`);
2. the stylesheets contributed by modules through
   `CanvasStylesheetProviderInterface` (see [creating-a-block.md](creating-a-block.md));
3. the stylesheet of the theme, `styles/app.css` as the asset mapper knows it;
4. the site styles set under **CMS > Settings**, when there are any.

It wraps the page in `<div class="cms-page-content">`, the class every
published page is wrapped in.

That is enough for a theme whose CSS is one compiled file styling
`.cms-page-content`. It is not enough for a theme that loads several sheets,
or whose CSS hangs on a class of `<html>` (`.brand body { font-family: … }`)
or on the container its page template puts around the content
(`.page__content p { … }`). Such a theme declares its canvas.

## The declaration

A file named `config/theliacms.yaml` at the root of the theme, next to
`config/views.yaml`:

```yaml
# templates/frontOffice/<your-theme>/config/theliacms.yaml
canvas:
    # What base.html.twig passes to asset(), in the same order. Replaces
    # styles/app.css instead of adding to it.
    stylesheets:
        - brand/lib/slider.css
        - brand/brand.scss
        - https://fonts.googleapis.com/css2?family=Brand&display=swap

    # The class attribute of <html> and <body> in base.html.twig.
    html_class: brand
    body_class: ''

    # The classes of the element cmspage.html.twig wraps the page content in.
    wrapper_class: page__content rich-text
```

With the base template of that theme reading:

```twig
<html lang="{{ lang_code }}" class="brand">
    <head>
        <link rel="stylesheet" href="{{ asset('brand/lib/slider.css') }}">
        <link rel="stylesheet" href="{{ asset('brand/brand.scss') }}">
        …
```

and its `cmspage.html.twig`:

```twig
<div class="page__content rich-text">
    {{ cms_page.html|raw }}
</div>
```

the canvas becomes:

```html
<html class="brand">
  <head>
    <link rel="stylesheet" href="/cms/blocks.css?v=…">
    <link rel="stylesheet" href="/assets/frontOffice/<your-theme>/brand/lib/slider-3b1f….css">
    <link rel="stylesheet" href="/assets/frontOffice/<your-theme>/brand/brand-9c2e….css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Brand&display=swap">
  </head>
  <body>
    <div class="cms-page-content page__content rich-text"> … the page … </div>
  </body>
</html>
```

## Rules

- Stylesheets are written the way the templates of the theme write them:
  an asset mapper path is resolved with `asset()` each time the editor opens,
  so the name of the last build is used (it changes with every build); a URL,
  or a path starting with `/`, is kept as it is. A declared list replaces
  `styles/app.css`; an empty list (`stylesheets: []`) loads no theme sheet at
  all. The socle, the module sheets and the site styles stay where they are.
- `html_class` and `body_class` go on `<html>` and `<body>` of the canvas
  document.
- `wrapper_class` goes on the element holding the page, next to
  `cms-page-content`. These classes are shown in the canvas and never saved
  with the page: the page template of the theme already wraps the published
  content in that container, and a saved copy would nest it twice on the front
  and follow the page into the next theme.
- Every key is optional. A child theme inherits each key it does not
  declare from the nearest parent that does (`<parent>` in `template.xml`).
  Declaring `html_class` alone in a child keeps the stylesheets of its parent.
- Without the file, or without a
  `canvas` key in it, the canvas loads what it loaded before.
- The `builder_stylesheet` setting of the site, when it is set, still wins over
  the declared stylesheets: it is the explicit choice of one site.
- A file that is there but wrong (invalid YAML, an unknown key, a stylesheet
  list that is not a list) stops the editor with a message naming the file,
  rather than leaving a canvas that silently looks wrong.

## Why a file in the theme

A Thelia theme is not always PHP. Flexy is a Symfony bundle, but a child theme
is often templates and assets only, and the active theme is a setting stored in
the database, not something the container knows when it is built. A file read
from the active theme and its parents works for both, follows a change of
theme without a cache clear, and follows the precedent of `config/views.yaml`,
which the core reads the same way.
