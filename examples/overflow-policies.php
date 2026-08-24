<?php

declare(strict_types=1);

use Nadar\PdfGenerator\Examples\BrandPdfSettings;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\TextBox;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/BrandPdfSettings.php';

$settings = new BrandPdfSettings();
$template = rtrim($settings->templatePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Offer.pdf';

if (!is_file($template)) {
    fwrite(STDERR, "Template not found: {$template}\n");
    fwrite(STDERR, "Add your own template and font files under examples/assets to run this example.\n");
    exit(1);
}

$text = 'This is intentionally long text to demonstrate overflow behavior in a constrained box.';

$pdf = (new PdfGenerator($settings))
    ->title('Overflow policies')
    ->page('Offer.pdf')
    ->write(new TextBox('none', 20, 25, 70, 8, overflow: Overflow::None), $text)
    ->write(new TextBox('shrink', 20, 40, 70, 8, overflow: Overflow::Shrink), $text)
    ->write(new TextBox('clip', 20, 55, 70, 8, overflow: Overflow::Clip), $text)
    ->write(new TextBox('truncate', 20, 70, 70, 8, overflow: Overflow::Truncate), $text)
    ->write(new TextBox('shrink-clip', 20, 85, 70, 8, overflow: Overflow::ShrinkThenClip), $text);

$output = __DIR__ . '/output/overflow-policies.pdf';
if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0755, true) && !is_dir(dirname($output))) {
    fwrite(STDERR, "Unable to create output directory: " . dirname($output) . "\n");
    exit(1);
}

if (file_put_contents($output, $pdf->bytes()) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

echo "Created {$output}\n";
