# The webmaster guide

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
