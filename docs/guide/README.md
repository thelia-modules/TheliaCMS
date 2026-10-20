# The webmaster guide

**Out of date since 2.0.0.** The guide, its PDF and its screenshots describe
the visual editor of the 1.x versions. Version 2.0.0 replaces it with the
content screen, where a page is written from a brief or section by section
([../generator.md](../generator.md)); the chapter on editing a page no longer
matches what a webmaster sees, and the screenshots of that screen show a
screen that is gone. The guide will be rewritten for the content screen. The
other chapters (menus, media, forms, settings, going live) still hold.

`thelia-cms-guide-webmaster.pdf` is the printable guide of the module, in
French: what each screen does, what the settings change, and the handful of
things worth checking once before a site goes live.

It is written as one HTML file and turned into a PDF by
[WeasyPrint](https://weasyprint.org), which understands page margins, running
footers and page counters:

```bash
python -m venv .venv && .venv/bin/pip install weasyprint
.venv/bin/weasyprint guide.html thelia-cms-guide-webmaster.pdf
```

`img/` holds screenshots of the real screens, taken at 1568 pixels wide, which
is about 250 dpi at the width they are printed. Retake them when a screen
changes rather than editing the old ones: a guide showing a button that moved
two versions ago costs more than no screenshot at all.

`fonts/` holds the two families of the guide, the same ones the `flexy-cms`
theme serves. They are files here so the PDF renders identically on any machine.
