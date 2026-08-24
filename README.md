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

## Handy examples

The repository includes runnable examples in [`/examples`](./examples):

- [`examples/basic-template.php`](./examples/basic-template.php): template-stamped page + layout writing
- [`examples/overflow-policies.php`](./examples/overflow-policies.php): all overflow modes in one output
- [`examples/BrandPdfSettings.php`](./examples/BrandPdfSettings.php): minimal settings implementation used by examples

## License notes

This package is MIT licensed. It depends on `tecnickcom/tcpdf` which is LGPL-3.0-or-later, so consumers must comply with TCPDF's LGPL obligations.

## Security defaults

`src/bootstrap.php` sets `K_TCPDF_CALLS_IN_HTML=false` and `K_TCPDF_THROW_EXCEPTION_ERROR=true` by default. This secures HTML rendering and changes TCPDF global error behavior from `die()` to exceptions.
