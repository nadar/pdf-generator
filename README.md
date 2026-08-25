# nadar/pdf-generator

Pixel-perfect template-overlay PDF generation on top of TCPDF and FPDI.

## Why use this package?

Most projects re-implement the same TCPDF/FPDI glue for fixed-layout documents. This package provides one typed facade with strict defaults so teams can reliably render template-based PDFs.

It helps you with:

- template-stamped pages with auto-derived size/orientation
- reusable typed text slots (`TextBox` / `Layout`)
- overflow policies (`Shrink`, `Clip`, `Truncate`, `ShrinkThenClip`)
- strict font/style handling to avoid silent bold/italic failures on embedded fonts
- deterministic PDF timestamps for reproducible output

## Requirements

- PHP 8.4 or 8.5
- `mbstring` and `zlib` extensions

## Installation

```bash
composer require nadar/pdf-generator
```

## Quick start

Create a settings class for paths, fonts, and defaults:

```php
<?php

namespace App\Pdf;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;

final class BrandPdfSettings extends AbstractPdfSettings
{
    public function fontPath(): string
    {
        return __DIR__ . '/fonts';
    }

    public function fontCachePath(): string
    {
        return __DIR__ . '/fonts/cache';
    }

    public function templatePath(): string
    {
        return __DIR__ . '/templates';
    }

    public function fonts(): FontSet
    {
        return FontSet::make()
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf', 'Inter-Italic.ttf', 'Inter-BoldItalic.ttf')
            ->role('regular', 'inter')
            ->role('headline', 'inter', 'B');
    }
}
```

Build/cache fonts once, then verify cache files exist:

```bash
vendor/bin/pdf-generator fonts:build --settings=App\\Pdf\\BrandPdfSettings
vendor/bin/pdf-generator fonts:check --settings=App\\Pdf\\BrandPdfSettings
```

Generate a PDF:

```php
<?php

use App\Pdf\BrandPdfSettings;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\PdfGenerator;

$layout = Layout::fromArray([
    ['id' => 'title', 'x' => 18, 'y' => 20, 'w' => 120, 'h' => 10, 'font' => 'headline', 'size' => 14],
    ['id' => 'body', 'x' => 18, 'y' => 35, 'w' => 170, 'h' => 60],
]);

$pdf = (new PdfGenerator(new BrandPdfSettings()))
    ->title('Offer')
    ->page('Offer.pdf')
    ->writeAll($layout, [
        'title' => 'ACME Offer #2026-001',
        'body' => "Hello Jane,\nThanks for your request.",
    ]);

$output = __DIR__ . '/output/offer.pdf';
if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0755, true) && !is_dir(dirname($output))) {
    throw new RuntimeException('Unable to create output directory: ' . dirname($output));
}

if (file_put_contents($output, $pdf->bytes()) === false) {
    throw new RuntimeException('Unable to write PDF: ' . $output);
}
```

## `PdfGenerator` API reference

| Method | Purpose |
| --- | --- |
| `raw()` | Access the wrapped `Fpdi` instance for low-level calls. |
| `__call()` | Proxy unknown methods to `Fpdi`; throws on missing methods. |
| `creator(string $creator)` | Set PDF creator metadata. |
| `author(string $author)` | Set PDF author metadata. |
| `title(string $title)` | Set PDF title metadata. |
| `subject(string $subject)` | Set PDF subject metadata. |
| `keywords(string $keywords)` | Set PDF keywords metadata. |
| `deterministic(int $timestamp)` | Set deterministic creation/modification timestamps. |
| `debug(bool $enabled)` | Enable or disable debug rendering helpers. |
| `page(?string $template = null, string|array|null $format = null, ?string $orientation = null, int $templatePage = 1, string $box = 'CropBox')` | Add a page, optionally stamp a template and auto-derive size/orientation. |
| `stamp(string $template, int $sourcePage = 1, string $box = 'CropBox')` | Stamp a template page on the current page. |
| `templateSize(string $template, int $page = 1)` | Read template page size as `PageSize`. |
| `assertTemplateSize(string $template, float $w, float $h, float $tolerance = 0.05)` | Assert expected template size. |
| `write(TextBox $box, string $text)` | Write text/HTML into a typed box with overflow handling. |
| `writeText(float $x, float $y, float $w, string $text, ?float $h = null, ?string $font = null, ?float $size = null, string $align = 'L', ?Overflow $overflow = null)` | Convenience writer for plain text. |
| `writeHtml(float $x, float $y, float $w, string $html, ?float $h = null, ?string $font = null, ?float $size = null, string $align = 'L', ?Overflow $overflow = null)` | Convenience writer for HTML content. |
| `writeRotated(float $x, float $y, float $w, float $angle, string $text, ?float $h = null, ?string $font = null, ?float $size = null, string $align = 'L', ?Overflow $overflow = null)` | Write rotated text. |
| `writeAll(iterable $boxes, array $data)` | Fill a full layout from keyed data fields. |
| `measureText(string $text, float $w, ?TextBox $box = null)` | Measure rendered plain-text height. |
| `measureHtml(string $html, float $w, ?TextBox $box = null)` | Measure rendered HTML height. |
| `fits(TextBox $box, string $text)` | Check whether text fits the box height. |
| `truncateToWidth(string $text, float $w, string $ellipsis = '...')` | Truncate text to target width. |
| `append(string $pdfBytes)` | Append all pages from PDF bytes. |
| `appendFile(string $path)` | Append all pages from an existing PDF file. |
| `bytes()` | Return final PDF as string bytes. |
| `save(string $path)` | Save final PDF to filesystem. |
| `debugGrid(float $step)` | Draw a debug grid on the current page (when debug is enabled). |

## Handy examples

The repository includes runnable examples in [`/examples`](./examples):

- [`examples/basic-template.php`](./examples/basic-template.php): template-stamped page + layout writing
- [`examples/overflow-policies.php`](./examples/overflow-policies.php): all overflow modes in one output
- [`examples/events-pagination.php`](./examples/events-pagination.php): large event list with automatic page breaks
- [`examples/BrandPdfSettings.php`](./examples/BrandPdfSettings.php): minimal settings implementation used by examples

## License notes

This package is MIT licensed. It depends on `tecnickcom/tcpdf` which is LGPL-3.0-or-later, so consumers must comply with TCPDF's LGPL obligations.

## Security defaults

`src/bootstrap.php` sets `K_TCPDF_CALLS_IN_HTML=false` and `K_TCPDF_THROW_EXCEPTION_ERROR=true` by default. This secures HTML rendering and changes TCPDF global error behavior from `die()` to exceptions.
