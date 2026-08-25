---
name: pdf-template-calibration
description: Reproduce a designer PDF pixel-perfect with nadar/pdf-generator. Use whenever a
  task supplies a template PDF - ideally together with a filled example - and asks to rebuild
  that layout in code. Extracts page box, fonts, exact font sizes, coordinates and colours from
  the reference, then runs a render/measure/adjust loop until the output matches. Triggers:
  "match this PDF", "rebuild this layout", "template overlay", "vorlage", "stamp our template",
  "make the PDF look like this", "pixel-perfect PDF", "recreate this print sheet".
---

# Calibrating a template overlay

The invariants this relies on are in `AGENTS.md` at the repository root. This file
is the procedure.

## 0. Inputs

Ask for two PDFs: the **template** (exactly as it will be stamped) and one
**filled example** of the finished layout. A screenshot is not enough - no text
layer means no measurable coordinates. If only the template exists, ask for a
filled export before writing any layout constants.

Also confirm the three things a PDF cannot express:

- the **data shape** you will be passed;
- the **overflow** behaviour (shrink? clip? both?);
- where **images** come from (local path or remote URL).

And one that is easy to miss: whether the placeholder styling in the filled
example is **normative or filler**. A real row and a placeholder row often differ,
and only one of them is the spec.

Needs `poppler-utils`; `python3` with `fonttools`, `pillow` and `numpy` for the
measuring steps.

## 1. Geometry and fonts

```bash
pdfinfo template.pdf filled.pdf      # the page box must match in both; note the pt values
pdffonts filled.pdf                  # the families and weights the design actually uses
```

Locate those faces as TrueType `.ttf`. Brand kits ship `.woff`/`.woff2`; convert
first, then build the cache - see `docs/fonts.md`.

Assert the geometry in code, so a re-export at the wrong size fails loudly:

```php
$pdf->assertTemplateSize('poster.pdf', 210.0, 297.0);
```

## 2. Positions

```bash
pdftotext -bbox filled.pdf -
```

Every word's box, in points. Convert with `mm = pt * 25.4 / 72`. Derive the repeat
pitch from **one slot measured across rows** - never by averaging unrelated lines.

## 3. Exact font sizes - do not eyeball

Solve the size from the measured width and the font's own advances:

```python
from fontTools.ttLib import TTFont
f = TTFont(ttf)
cmap, hmtx, upem = f.getBestCmap(), f['hmtx'], f['head'].unitsPerEm
adv = lambda s: sum(hmtx[cmap[ord(c)]][0] for c in s) / upem

size = measured_width_pt / adv(word)
```

Run it per candidate weight. The weight whose answers agree across several words
is the one in use, and the agreed number is the size. This is the only reliable
way to tell Medium from Bold.

## 4. Colours and non-text geometry

```bash
pdftoppm -r 200 -png filled.pdf ref
pdfimages -all filled.pdf img        # extract embedded photos for a demo asset
```

The modal pixel of a text region is the text colour. Colour-mask circles, rects
and codes to get their centres and diameters, which the text layer cannot give
you.

## 5. Build with the anchor stated

Write the layout with constants in mm, and **do not** hand-correct the vertical
offset. `TextBox::y` is the cell top; the reference gives you baselines. Say so:

```php
new TextBox('title', x: 53.2, y: 58.48, w: 120, font: 'bold', size: 24, anchor: Anchor::Baseline);
```

## 6. Calibrate in-process

`probe()` resolves a box without drawing, so the loop needs no external tooling:

```php
$m = $pdf->probe($titleBox, $sampleTitle);
printf("baseline %.3f  size %.2fpt  lines %d\n", $m->baseline, $m->size, $m->lines);
```

Compare against the reference value, adjust the constant, repeat. Converge until
the delta is below the reference's own row-to-row drift - typically under 0.5 pt.

`debug(true)` draws each box, its baseline and its resolved size, which is the
fast way to see *which* slot is wrong.

Fall back to pixels only for what `probe()` cannot report:

```python
d = np.abs(img[y0:y1, x0:x1] - np.array(text_rgb)).sum(axis=2)
rows = np.where((d < 120).any(axis=1))[0]     # contiguous bands = text lines
```

Use tight windows: a loose y-window catches the neighbouring line and produces a
bogus delta.

## 7. Handle the states the design shows you

The filled example usually specifies more than the happy path. A coloured circle
where a photo is missing is the **designed** fallback, so implement it as one:

```php
$pdf->image(
    ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85, placeholder: Color::hex('#ff920c')),
    $event['image'],
);
```

Likewise, a long title in a fixed slot means `maxLines: 1` with a `minSize` floor,
not a hope that the data stays short.

## 8. Report

State the values you derived - sizes, pitch, colours, weights - so a human can
sanity-check them against the design. Keep them as named constants in one place,
so a design revision is a one-file change. Then pin the result with a golden-file
test (`docs/testing-generated-pdfs.md`) so a font update cannot silently reflow
the print run.
