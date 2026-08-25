# nadar/pdf-generator

Pixel-perfect template-overlay PDF generation on top of TCPDF and FPDI.

## Why use this package?

Most projects re-implement the same TCPDF/FPDI glue for fixed-layout documents:
stamp a designer's export, then place text, photos and codes at coordinates
measured off the design. This package is that glue, typed and with strict
defaults.

- **Template-stamped pages** with size and orientation derived from the template
- **Typed slots** - text, image and code slots share one `Layout`, so a repeated row moves as a unit
- **Design coordinates as-is**: `Anchor::Baseline` takes the baseline a design gives you
- **A measurement API**: `probe()` reports where text will land, without drawing
- **Overflow policies** (`Shrink`, `Clip`, `Truncate`, `ShrinkThenClip`) and a line cap
- **Images** with cover/contain fit, circular and rounded clipping, and designed fallbacks
- **QR codes and barcodes** defaulting to transparent-on-artwork
- **Strict fonts**, so a missing bold face fails loudly instead of printing wrong
- **Byte-stable output** for golden-file tests and HTTP caching, codes included

## Requirements

- PHP 8.4 or 8.5
- `mbstring` and `zlib` extensions

## Installation

```bash
composer require nadar/pdf-generator
```

## Quick start

### 1. Describe your document once

```php
namespace App\Pdf;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\Value\Color;

final class BrandPdfSettings extends AbstractPdfSettings
{
    public function fontPath(): string      { return __DIR__ . '/fonts'; }
    public function fontCachePath(): string { return __DIR__ . '/fonts/cache'; }
    public function templatePath(): string  { return __DIR__ . '/templates'; }

    public function fonts(): FontSet
    {
        return FontSet::make()
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
            ->role('regular', 'inter')
            ->role('bold', 'inter', 'bold');
    }

    public function textColor(): Color   { return Color::hex('#223764'); }

    // the safe choice for anything data-driven: never throws on a long value
    public function overflow(): Overflow { return Overflow::ShrinkThenClip; }
}
```

### 2. Compile the fonts once

TCPDF cannot embed a `.ttf` directly, so every face is converted ahead of
rendering. The cache must exist before the first render - commit it, or build it
in CI.

```bash
vendor/bin/pdf-generator fonts:build --settings="App\Pdf\BrandPdfSettings"
vendor/bin/pdf-generator fonts:check --settings="App\Pdf\BrandPdfSettings"
```

If your settings class needs a booted framework, pass plain paths instead:

```bash
vendor/bin/pdf-generator fonts:build --fonts=resources/pdf/fonts --cache=resources/pdf/fonts/cache
```

See [docs/fonts.md](docs/fonts.md) for formats, `.woff` conversion and CI.

### 3. Render

A row's geometry - text, photo and code - is one `Layout`, so `repeat()` moves
all of it and the loop carries no offset arithmetic:

```php
use Nadar\PdfGenerator\{Align, ImageBox, Layout, PdfGenerator, QrBox, TextBox};
use Nadar\PdfGenerator\Value\Color;

$row = Layout::fromArray([
    ['id' => 'title', 'x' => 53.2, 'y' => 58.48, 'w' => 120, 'h' => 11.5, 'font' => 'bold', 'size' => 24, 'maxLines' => 1],
    ['id' => 'meta',  'x' => 53.2, 'y' => 70.11, 'w' => 120, 'h' => 9.5,  'font' => 'bold', 'size' => 19, 'maxLines' => 1],
])->with(
    ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85, placeholder: Color::hex('#ff920c')),
    new QrBox('link', x: 179, y: 58.63, size: 19.5, color: Color::hex('#223764')),
);

$pdf = (new PdfGenerator(new BrandPdfSettings()))
    ->title('June highlights')
    ->page('poster-template.pdf');

$pdf->write(new TextBox('month', x: 20, y: 28.35, w: 170, size: 25, align: Align::Center), 'June');

foreach ($row->repeat(times: 6, dy: 40.3) as $index => $slots) {
    $event = $events[$index] ?? null;
    if ($event === null) {
        break;
    }

    $pdf->writeAll($slots, $event);                        // text slots
    $pdf->image($slots->image('photo'), $event['image']);  // image slot
    $pdf->qr($slots->qr('link'), $event['url']);           // code slot
}

file_put_contents('poster.pdf', $pdf->bytes());
```

## Reproducing an existing design

The fastest way to a pixel-perfect overlay - whether the person doing it is new
to the codebase or an AI agent - is to hand over **two PDFs**:

1. **The template**, exactly as it will be stamped (`page('template.pdf')`).
2. **One filled example** of the finished layout, with placeholder rows where content repeats.

The filled example is not decoration, it is a measurable spec: page box, font
names and weights, sizes, x/y positions, row pitch and brand colours are all
extractable from it, and your output can be diffed against it numerically until
it matches. Add three lines of prose a PDF cannot express - the **data shape**
you will pass in, what should happen on **overflow**, and where **images** come
from - and the brief is complete.

Coordinates measured off a design are **baselines**, while `TextBox::y` is the
top of the first line's cell. Say which you mean rather than correcting by hand:

```php
new TextBox('title', x: 53.2, y: 58.48, w: 120, size: 24, anchor: Anchor::Baseline);
```

...and read the result back without leaving PHP:

```php
$metrics = $pdf->probe($titleBox, $event['title']);
// $metrics->baseline, ->size (after any shrink), ->lines, ->box
```

See [docs/matching-a-template.md](docs/matching-a-template.md) for the extraction
commands and the calibration loop, and
[skills/pdf-template-calibration](skills/pdf-template-calibration/SKILL.md) if you
work with an AI agent.

## Documentation

| Guide | |
| --- | --- |
| [AGENTS.md](AGENTS.md) | The invariants, in 60 lines. Start here. |
| [docs/matching-a-template.md](docs/matching-a-template.md) | Reproducing an existing design |
| [docs/fonts.md](docs/fonts.md) | Formats, conversion, the cache, multi-weight families, CI |
| [docs/images-and-shapes.md](docs/images-and-shapes.md) | Fit and clip modes, missing-image fallbacks |
| [docs/codes-and-qr.md](docs/codes-and-qr.md) | QR on artwork, error correction, barcodes |
| [docs/repeated-slots-and-pagination.md](docs/repeated-slots-and-pagination.md) | Repeated rows and page breaks |
| [docs/framework-integration.md](docs/framework-integration.md) | Laravel, Symfony, serverless, custom TCPDF subclasses |
| [docs/testing-generated-pdfs.md](docs/testing-generated-pdfs.md) | Metrics assertions, golden files, stream inspection |

## `PdfGenerator` API reference

Every method carries a full docblock; this is the inventory.

### Document

| Method | Purpose |
| --- | --- |
| `page(?string $template, string\|array\|null $format, ?string $orientation, int $templatePage, string $box)` | Add a page, optionally stamping a template and deriving its size/orientation. |
| `stamp(string $template, int $sourcePage, string $box)` | Stamp a template onto the current page. |
| `templateSize(string $template, int $page)` | Read a template's page size as a `PageSize`. |
| `assertTemplateSize(string $template, float $w, float $h, float $tolerance)` | Fail loudly on a re-export at the wrong size (0.5 mm tolerance by default). |
| `append(string $pdfBytes)` / `appendFile(string $path)` | Append every page of another PDF. |
| `bytes()` / `save(string $path)` | Render the document. Both close it. |

### Text

| Method | Purpose |
| --- | --- |
| `write(TextBox $box, string $text)` | Write into a typed slot, applying its overflow policy and anchor. |
| `writeAll(iterable $boxes, array $data)` | Fill a `Layout`'s text slots from keyed data; other slots are skipped. |
| `writeText()` / `writeHtml()` / `writeRotated()` | Convenience writers without building a `TextBox`. |
| `probe(TextBox $box, string $text)` | Resolve a box's geometry **without drawing**. |
| `lastMetrics()` | The geometry of the most recent `write()`. |
| `measureText()` / `measureHtml()` / `lineCount()` / `fits()` | Measure text at a given width. |
| `truncateToWidth(string $text, float $w, string $ellipsis)` | Cut at a word boundary to fit a width. |

### Graphics

| Method | Purpose |
| --- | --- |
| `image(ImageBox $box, ?string $source, ?callable $onMissing)` | Place an image, scaled and clipped to its box. |
| `fillShape(ImageBox $box, Color $color)` | Paint a slot's outline - the fill a `placeholder:` performs. |
| `qr(QrBox $box, string $data)` | Draw a QR code; transparent, unpadded and reproducible by default. |
| `qrAt(string $data, float $x, float $y, float $size, ...)` | The same, at explicit coordinates. |
| `barcode1d(BarcodeBox $box, string $data)` | Draw a linear barcode. |
| `barcode1dAt(string $data, Barcode1D $type, ...)` | The same, at explicit coordinates. |
| `imageLoader()` | The image resolver, for inspecting or clearing its cache. |

### Metadata and diagnostics

| Method | Purpose |
| --- | --- |
| `title()` / `subject()` / `keywords()` / `creator()` / `author()` | PDF metadata. |
| `deterministic(int $timestamp, ?string $id)` | Pin timestamps and document id for byte-stable output. |
| `debug(bool $enabled)` | Draw box outlines, baselines and resolved metrics. |
| `debugGrid(float $step)` | Draw a measuring grid. |
| `raw()` | The wrapped `Fpdi`, for anything not covered here. |
| `__call()` | Proxy unknown methods to `Fpdi`; throws on a name it does not have. |

## Examples

Runnable with no assets - they generate their own templates and fall back to
TCPDF's core fonts:

```bash
php examples/template-overlay-poster.php
```

- [`template-overlay-poster.php`](./examples/template-overlay-poster.php): stamped template, repeated rows, circular images with a designed fallback, per-row QR codes, shrink-to-one-line titles, pagination
- [`basic-template.php`](./examples/basic-template.php): stamp a template, fill a layout from keyed data
- [`overflow-policies.php`](./examples/overflow-policies.php): every policy with its resolved size and line count
- [`events-pagination.php`](./examples/events-pagination.php): a long list across pages
- [`BrandPdfSettings.php`](./examples/BrandPdfSettings.php): the settings implementation they share

## Licence notes

This package is MIT licensed. It depends on `tecnickcom/tcpdf`, which is
LGPL-3.0-or-later, so consumers must comply with TCPDF's LGPL obligations.

One consequence is visible in the output: TCPDF writes its attribution
("Powered by TCPDF") at 1 pt into the bottom edge of the last page of every
document. That line is TCPDF's, not this package's - it is not a defect, and the
string is hex-obfuscated in TCPDF's source, which is why grepping for it finds
nothing. See [docs/framework-integration.md](docs/framework-integration.md#the-powered-by-tcpdf-line).

## Security defaults

`src/bootstrap.php` sets `K_TCPDF_CALLS_IN_HTML=false`, which blocks TCPDF method
calls embedded in HTML input - otherwise a code-execution path for user content -
and `K_TCPDF_THROW_EXCEPTION_ERROR=true`, which turns TCPDF's internal `die()`
into catchable exceptions. Both are guarded, so an application can define them
differently beforehand.

Escape any user-supplied value going into an `html: true` box with
`Text::forHtmlCell()`.
