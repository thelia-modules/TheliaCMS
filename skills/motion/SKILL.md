---
name: motion
description: How a component moves or reacts with its stylesheet alone, and what it leaves to the theme.
---

# Movement and interaction without script

A component of the site runs no script: what moves, moves in its stylesheet.
The page filters out what is not HTML text and layout (`input`, `animate`, a `<svg>` that moves), so a
movement is never drawn in markup.

- A picture that scrolls on its own: a frame (`overflow: hidden`,
  `tabindex="0"` so the keyboard reaches it) holding a track with the picture
  twice in a row, the second with an empty `alt` and `aria-hidden="true"`.
  The track is twice as long as the frame and goes from `translateX(0)` to
  `translateX(-50%)`, `linear infinite`, so the loop has no seam. Vertically,
  the track is a column twice as tall and goes to `translateY(-50%)`. A prop
  picks the direction or the speed through a class, and the stylesheet sets
  the `animation` of each class.
- It stops under the pointer and on focus: `animation-play-state: paused` on
  `.frame:hover .track` and `.frame:focus-within .track`.
- Every animation sits in `@media (prefers-reduced-motion: no-preference)`.
  Without it the still picture shows, whole and readable: that is the
  component, the movement is extra.
- Questions, folds, a part that opens: `<details>` and `<summary>`; give the
  same `name` to the `<details>` of a group to keep one open at a time.
- Cards that slide: a list with `overflow-x: auto` and `scroll-snap-type`,
  moved by the visitor, never by itself.
- A hover or a focus effect: a `transition` on what changes, the same effect
  on `:hover` and on `:focus-visible`.
- Tabs that keep a state, a carousel that turns with buttons, a counter, a
  form: they need a script. Draw the closest the stylesheet allows, a list
  that scrolls by hand rather than a carousel that turns by itself, and say
  so in the description; the theme ships such components with their
  controller.
