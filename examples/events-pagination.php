<?php

declare(strict_types=1);

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\PdfGenerator;

require dirname(__DIR__) . '/vendor/autoload.php';

final class EventsPaginationSettings extends AbstractPdfSettings
{
    public function fontPath(): string
    {
        return __DIR__;
    }

    public function fontCachePath(): string
    {
        return __DIR__;
    }

    public function templatePath(): string
    {
        return __DIR__;
    }

    public function fonts(): FontSet
    {
        return FontSet::make();
    }
}

/** @return list<array{date:string,title:string,location:string}> */
function buildEvents(int $count): array
{
    $rows = [];
    $base = new DateTimeImmutable('2026-01-01 09:00:00');

    for ($i = 0; $i < $count; ++$i) {
        $date = $base->modify(sprintf('+%d day', $i));
        $rows[] = [
            'date' => $date->format('Y-m-d H:i'),
            'title' => sprintf('Event %03d', $i + 1),
            'location' => sprintf('Room %d', ($i % 12) + 1),
        ];
    }

    return $rows;
}

$settings = new EventsPaginationSettings();
$pdf = (new PdfGenerator($settings))->title('Events list');

$events = buildEvents(180);
$lineHeight = 6.0;
$top = 15.0;
$left = 15.0;
$y = $top;
$page = 0;

$startPage = static function () use (&$pdf, &$page, &$y, $top, $left): void {
    ++$page;
    $y = $top;
    $pdf->page();
    $pdf->writeText($left, $y, 180, sprintf('Event list (page %d)', $page), 6, null, 12);
    $y += 8;
};

$startPage();

foreach ($events as $event) {
    if ($y >= 285) {
        $startPage();
    }

    $pdf->writeText($left, $y, 45, $event['date'], $lineHeight);
    $pdf->writeText($left + 48, $y, 80, $event['title'], $lineHeight);
    $pdf->writeText($left + 130, $y, 50, $event['location'], $lineHeight);
    $y += $lineHeight;
}

$output = __DIR__ . '/output/events-pagination.pdf';
$outputDir = dirname($output);
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

if (file_put_contents($output, $pdf->bytes()) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

echo "Created {$output}\n";
