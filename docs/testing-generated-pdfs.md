# Testing generated PDFs

A print run is expensive to get wrong, and a font update can silently reflow a
layout. Three levels of checking, cheapest first.

## 1. Assert the resolved geometry

`probe()` resolves a box against some text and returns where it would land,
without drawing. This is the fastest and most precise check available, and it
needs no external tooling:

```php
$metrics = $pdf->probe($titleBox, 'A remarkably long event title');

self::assertEqualsWithDelta(58.48, $metrics->baseline, 0.01);
self::assertSame(1, $metrics->lines);
self::assertGreaterThanOrEqual(13.0, $metrics->size);   // it shrank, but stayed legible
```

`lastMetrics()` returns the same thing after a `write()`. `TextMetrics::toArray()`
is flat, which makes it easy to dump a whole calibration run.

Assert *behaviour* rather than exact pixels wherever you can: "the title fits on
one line and never drops below 13 pt" survives a copy change; "the baseline is at
58.48" does not.

## 2. Golden files

`deterministic()` makes the bytes reproducible - it pins the timestamps *and* the
document id TCPDF otherwise seeds randomly. Without it, two renders of identical
input differ and a hash comparison is useless.

```php
public function testPosterMatchesTheGoldenFile(): void
{
    $pdf = (new PdfGenerator(new BrandPdfSettings()))
        ->deterministic(1_700_000_000)
        ->title('Highlights');

    $bytes = (new Poster($pdf))->render('June', self::fixtureEvents());

    self::assertSame(
        md5((string) file_get_contents(__DIR__ . '/golden/poster.pdf')),
        md5($bytes)
    );
}
```

Regenerate deliberately, and review the diff of what changed in the design before
accepting a new golden file. A golden file that is updated reflexively catches
nothing.

Keep the *input* fixed too: a golden test that reads live data or fetches a remote
image is not a golden test. Point images at local fixtures.

## 3. Inspecting the output

To assert something the metrics API does not report, read the PDF itself. With
compression off the content stream is plain text:

```php
$pdf->raw()->SetCompression(false);
// ...
$bytes = $pdf->bytes();
```

TCPDF emits `BT <x> <y> Td [(text)] TJ` for every text run, with the position in
PDF user space. So the *actual* rendered baseline is:

```php
preg_match_all('/BT\s+(-?[\d.]+)\s+(-?[\d.]+)\s+Td\s*\[\((.*?)\)\]\s*TJ/s', $bytes, $matches, PREG_SET_ORDER);

$baselineMm = 297.0 - ((float) $matches[0][2]) / (72 / 25.4);
```

This package's own suite does exactly that - see
[`tests/Integration/IntegrationTestCase.php`](../tests/Integration/IntegrationTestCase.php)
for reusable `textOps()`, `baselines()` and `drawnText()` helpers.

Two traps when writing such assertions:

- **`/Image` matches every PDF.** Every document declares
  `/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]`. Check for `/Subtype /Image` to
  detect an actually embedded image.
- **TCPDF's attribution line is always there.** It draws "Powered by TCPDF" at
  1 pt on the last page, so filter it out of any text-op assertion.

## 4. Pixels, when you must

For colours and non-text geometry there is no substitute for rasterising:

```bash
pdftoppm -r 200 -png out.pdf render      # needs poppler-utils
```

```python
import numpy as np
from PIL import Image

img = np.array(Image.open('render-1.png').convert('RGB')).astype(int)
d = np.abs(img[y0:y1, x0:x1] - np.array([34, 55, 100])).sum(axis=2)
rows = np.where((d < 120).any(axis=1))[0]     # contiguous bands = text lines
```

Use this for the things the text layer cannot express - brand colours, circle
diameters, code bounds - and `probe()` for everything vertical.

## Testing without font binaries

Font files are large and separately licensed, so committing them to a test suite
is often not an option. `coreFamily()` registers a TCPDF built-in, which needs no
compilation and still gives a real bold face:

```php
FontSet::make()
    ->coreFamily('helvetica')
    ->role('regular', 'helvetica')
    ->role('bold', 'helvetica', 'bold');
```

Core-font metrics differ from your brand font, so a golden file built on them
only proves the *layout logic* is stable, not that the print is right. Use real
fonts for the golden test that guards the actual design, and gate it on the
fixture being present - as this repository does in
[`tests/Fixtures/fonts/README.md`](../tests/Fixtures/fonts/README.md).
