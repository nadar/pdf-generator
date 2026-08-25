<?php

declare(strict_types=1);

/**
 * The smallest useful shape: stamp a template, fill a layout from keyed data.
 *
 * The template is generated on the first pass so the example runs with no
 * assets; in a real project it is a designer-exported PDF under
 * templatePath().
 *
 * Usage: php examples/basic-template.php
 */

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Examples\BrandPdfSettings;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\TextBox;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/BrandPdfSettings.php';

$settings = new BrandPdfSettings();

$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

// Stand in for the designer's export.
$template = $outputDir . '/Offer.pdf';
if (!is_file($template)) {
    $blank = (new PdfGenerator($settings))->title('Offer template');
    $blank->page(null, 'A4');
    $blank->write(new TextBox('brand', x: 18, y: 12, w: 174, font: 'headline', size: 20), 'ACME');
    $blank->raw()->Line(18, 22, 192, 22);
    $blank->save($template);
}

/*
 * The geometry lives in one place, measured once off the design; the data is
 * whatever the request supplies. Slot ids are the data keys.
 */
$layout = Layout::fromArray([
    ['id' => 'title', 'x' => 18, 'y' => 35, 'w' => 120, 'h' => 10, 'font' => 'headline', 'size' => 14],
    ['id' => 'customer', 'x' => 18, 'y' => 48, 'w' => 170, 'h' => 8],
    ['id' => 'body', 'x' => 18, 'y' => 60, 'w' => 170, 'h' => 50],
    ['id' => 'total', 'x' => 18, 'y' => 120, 'w' => 170, 'h' => 10, 'font' => 'headline', 'size' => 14, 'align' => 'right'],
]);

$pdf = (new PdfGenerator($settings))
    ->title('Offer')
    // byte-stable output, so a golden-file test can pin this document
    ->deterministic(1_700_000_000)
    ->page('Offer.pdf')
    ->writeAll($layout, [
        'title' => 'Offer #2026-001',
        'customer' => 'Jane Doe',
        'body' => "Hello Jane,\nThanks for your request. The quote below is valid for 30 days.",
        'total' => 'Total: EUR 1,240.00',
    ]);

// Anything the layout does not cover is still one call away.
$pdf->write(
    new TextBox('footer', x: 18, y: 280, w: 174, size: 8, align: Align::Center),
    'ACME GmbH - Example Street 1 - 8000 Zurich'
);

$output = $outputDir . '/basic-template.pdf';
if (file_put_contents($output, $pdf->bytes()) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

echo "Created {$output}\n";
