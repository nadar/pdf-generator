<?php

declare(strict_types=1);

/**
 * Every overflow policy on one page, plus the line cap, with the resolved size
 * of each printed next to it.
 *
 * The point of the printout: shrinking measures *height*, so wide text wraps
 * first and shrinks second. `maxLines` is what expresses "keep this on one
 * line, whatever size that takes".
 *
 * Usage: php examples/overflow-policies.php
 */

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Examples\BrandPdfSettings;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\TextBox;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/BrandPdfSettings.php';

$settings = new BrandPdfSettings();
$pdf = (new PdfGenerator($settings))->title('Overflow policies');
$pdf->page(null, 'A4');
$pdf->debug(true);

$text = 'This is intentionally long text to demonstrate overflow behaviour in a constrained box.';

/** @var array<string,TextBox> $cases */
$cases = [
    'None - spills out of the box' => new TextBox('none', 20, 0, 70, 12, size: 14, overflow: Overflow::None),
    'Shrink - throws if it cannot fit' => new TextBox('shrink', 20, 0, 70, 12, size: 14, minSize: 5.0, overflow: Overflow::Shrink),
    'Clip - cut at the box edge' => new TextBox('clip', 20, 0, 70, 12, size: 14, overflow: Overflow::Clip),
    'Truncate - one line with an ellipsis' => new TextBox('truncate', 20, 0, 70, 12, size: 14, overflow: Overflow::Truncate),
    'ShrinkThenClip - never throws' => new TextBox('shrink-clip', 20, 0, 70, 12, size: 14, overflow: Overflow::ShrinkThenClip),
    'ShrinkThenClip + maxLines: 1' => new TextBox('one-line', 20, 0, 70, 12, size: 14, minSize: 5.0, overflow: Overflow::ShrinkThenClip, maxLines: 1),
];

$y = 30.0;
foreach ($cases as $label => $box) {
    $pdf->write(new TextBox('label', 20, $y, 170, size: 9, font: 'headline'), $label);

    $placed = $box->with(y: $y + 6.0);
    $pdf->write($placed, $text);

    // probe() reports the same geometry without drawing, which is what a
    // calibration script uses.
    $metrics = $pdf->lastMetrics();
    if ($metrics !== null) {
        $pdf->write(
            new TextBox('resolved', 100, $y, 90, size: 9, align: Align::Right),
            sprintf('%.2fpt / %d line(s)', $metrics->size, $metrics->lines)
        );
    }

    $y += 32.0;
}

/*
 * Shrink is the one policy that throws: good for content you control, wrong
 * for a loop over CMS data where one long title should not become a 500.
 */
$tiny = new TextBox('too-small', 20, $y + 6, 40, 4, size: 14, overflow: Overflow::Shrink);
$pdf->write(new TextBox('label', 20, $y, 170, size: 9, font: 'headline'), 'Shrink in a box that is far too small');

try {
    $pdf->write($tiny, $text);
} catch (\Nadar\PdfGenerator\Exception\OverflowException $exception) {
    $pdf->write(
        new TextBox('caught', 20, $y + 6, 170, size: 8),
        'OverflowException: ' . $exception->getMessage()
    );
}

$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

$output = $outputDir . '/overflow-policies.pdf';
if (file_put_contents($output, $pdf->bytes()) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

echo "Created {$output}\n";
