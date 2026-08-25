# Matching an existing design

The task: a designer hands over a print PDF, and the output has to match it
pixel for pixel. This is the page to read first, because the numbers are
*extractable* - there is no reason to guess a single coordinate.

## Ask for two PDFs

The single biggest factor in getting this right on the first pass is the input
format. Ask for:

1. **The template**, exactly as it will be stamped - the same file `page()` will load.
2. **One filled example** of the finished layout, with placeholder rows where content repeats.

Why this beats a written spec:

- The empty file *is* the production asset. Background art, logo and any fixed
  headline come for free through `stamp()`; the code only draws the delta.
- The filled file is a machine-readable spec. Page box, font names and weights,
  sizes, positions, row pitch and brand colours are all measurable from it.
- Same page geometry in both means **the diff is the spec**: whatever appears in
  the second and not the first is exactly the code's job.
- It encodes intent prose would miss. Placeholder rows reveal the repeat count
  and pitch. The one *real* row reveals the true styling versus filler. A QR code
  on row 1 only reveals a QR slot and its exact position. A coloured "image"
  circle tells you what to draw when an image is missing - so the fallback is a
  designed state rather than your invention.

A screenshot is dramatically weaker: no text layer, unknown scale, no font
names, no embedded images. A design-tool link is weaker still for anything
automated. **Export the PDF.**

Three things a PDF cannot express, worth asking for in prose:

- the **data shape** you will be passed;
- what should happen on **overflow** (shrink? clip? both?);
- where **images** come from (local path or remote URL).

## Extract the numbers

All of this is `poppler-utils` plus Python.

```bash
pdfinfo template.pdf filled.pdf      # page box; it must match in both
pdffonts filled.pdf                  # which families and weights the design uses
pdftotext -bbox filled.pdf -         # every word's box, in points
pdftoppm -r 200 -png filled.pdf ref  # pixel truth for colours and non-text geometry
pdfimages -all filled.pdf img        # pull embedded photos out for a demo asset
```

- **`pdffonts`** names the brand font and proves which weight each line uses -
  which no visual inspection settles. It also tells you which faces to compile.
- **`pdftotext -bbox`** is the goldmine: exact x positions, and the row pitch from
  the same slot across several rows. Convert with `mm = pt * 25.4 / 72`, or
  `Units::ptToMm()`. Derive the pitch from *one* slot repeated, never by averaging
  unrelated lines.
- **`pdftoppm` + numpy** covers what the text layer cannot: exact colours (the modal
  pixel of a text region) and non-text geometry (circle diameters and centres, code bounds).

### Solve the font size instead of eyeballing it

Take a word's measured advance width from `-bbox`, divide by the sum of the
glyph advances from the font, and the quotient *is* the size:

```python
from fontTools.ttLib import TTFont
f = TTFont(ttf)
cmap, hmtx, upem = f.getBestCmap(), f['hmtx'], f['head'].unitsPerEm
adv = lambda s: sum(hmtx[cmap[ord(c)]][0] for c in s) / upem

size = measured_width_pt / adv('Event Highlights')
```

Run it against each candidate weight. The weight whose answers agree across
several words is the one the design uses, and the agreed number is the size.

## Calibrate

The reason a reference PDF beats a spec is that it closes the loop:

```
render -> measure -> diff against the reference -> adjust -> repeat
```

The first render is normally off vertically, because the reference gives you
*ink* positions while `TextBox::y` is the **cell top** (see
[AGENTS.md](../AGENTS.md) for the exact relation). Two ways to deal with that:

**Preferred - state the anchor and skip the correction entirely.** A baseline
measured off the reference can be used verbatim:

```php
new TextBox('title', x: 53.2, y: 58.48, w: 120, font: 'bold', size: 24, anchor: Anchor::Baseline);
```

**Then verify in-process** with `probe()`, which resolves a box without drawing:

```php
$metrics = $pdf->probe($titleBox, $event['title']);

printf("baseline %.3f mm, %.2fpt, %d line(s)\n", $metrics->baseline, $metrics->size, $metrics->lines);
// compare against the reference value and adjust the constant
```

`probe()` removes the external-tooling dependency for the vertical pass
altogether. Reach for pixels only for what the library cannot report - colours,
circles, code bounds:

```python
d = np.abs(img[y0:y1, x0:x1] - np.array(text_rgb)).sum(axis=2)
rows = np.where((d < 120).any(axis=1))[0]      # contiguous bands = text lines
```

Converge until the delta is below the reference's own row-to-row drift -
typically under 0.5 pt. Use tight windows: a loose y-window catches the
neighbouring line and produces a bogus delta.

## Lock it in

- Keep every measured value as a named constant in one place, so a design
  revision is a one-file change.
- Assert the template geometry, so a re-export at the wrong page size fails loudly
  instead of shifting everything: `$pdf->assertTemplateSize('poster.pdf', 210.0, 297.0)`.
  Design tools do not export exact ISO sizes - Canva's "A4" is 210.079 x 297.127 mm - so the
  default tolerance is a generous 0.5 mm, which still separates any two formats
  you could confuse. `pdfinfo` or `templateSize()` gives you the real numbers if
  you want to assert those instead.
- Add a golden-file test (see [testing-generated-pdfs.md](testing-generated-pdfs.md)) so a
  font update cannot silently reflow a print run.
- State the values you derived in your commit message or PR, so a human can
  sanity-check them against the design.

## Turning on the debug overlay

`debug(true)` draws each declared box, its id, the first baseline, and the
resolved size and line count next to it. `debugGrid(10)` adds a measuring grid.
Both draw into the document - development only.
