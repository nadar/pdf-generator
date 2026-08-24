<?php

declare(strict_types=1);

use Nadar\PdfGenerator\Examples\BrandPdfSettings;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\PdfGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/BrandPdfSettings.php';

$settings = new BrandPdfSettings();
$template = rtrim($settings->templatePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Offer.pdf';

if (!is_file($template)) {
    fwrite(STDERR, "Template not found: {$template}\n");
    fwrite(STDERR, "Add your own template and font files under examples/assets to run this example.\n");
    exit(1);
}

$layout = Layout::fromArray([
    ['id' => 'title', 'x' => 18, 'y' => 20, 'w' => 120, 'h' => 10, 'font' => 'headline', 'size' => 14],
    ['id' => 'customer', 'x' => 18, 'y' => 35, 'w' => 170, 'h' => 8],
    ['id' => 'body', 'x' => 18, 'y' => 48, 'w' => 170, 'h' => 50],
]);

$pdf = (new PdfGenerator($settings))
    ->title('Offer')
    ->page('Offer.pdf')
    ->writeAll($layout, [
        'title' => 'Offer #2026-001',
        'customer' => 'Jane Doe',
        'body' => "Hello Jane,\nThanks for your request.",
    ]);

$output = __DIR__ . '/output/basic-template.pdf';
if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0755, true) && !is_dir(dirname($output))) {
    fwrite(STDERR, "Unable to create output directory: " . dirname($output) . "\n");
    exit(1);
}

if (file_put_contents($output, $pdf->bytes()) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

echo "Created {$output}\n";
