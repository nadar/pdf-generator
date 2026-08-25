<?php

declare(strict_types=1);

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\Overflow;
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

    /** Core fonts need no compilation, so this example needs no assets. */
    public function fonts(): FontSet
    {
        return FontSet::make()
            ->coreFamily('helvetica')
            ->role('regular', 'helvetica')
            ->role('bold', 'helvetica', 'bold');
    }

    /** Row content is generated, so clip rather than throw on a long value. */
    public function overflow(): Overflow
    {
        return Overflow::ShrinkThenClip;
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

const ROW_PITCH = 6.0;
const ROWS_PER_PAGE = 44;
const TOP = 25.0;
const LEFT = 15.0;

/*
 * One row's geometry, declared once. repeat() turns it into the page's rows,
 * which keeps the "$index * $pitch" arithmetic out of the render loop.
 */
$rowLayout = Layout::fromArray([
    ['id' => 'date', 'x' => LEFT, 'y' => TOP, 'w' => 45, 'h' => ROW_PITCH, 'size' => 9],
    ['id' => 'title', 'x' => LEFT + 48, 'y' => TOP, 'w' => 80, 'h' => ROW_PITCH, 'size' => 9],
    ['id' => 'location', 'x' => LEFT + 130, 'y' => TOP, 'w' => 50, 'h' => ROW_PITCH, 'size' => 9, 'align' => 'right'],
]);

$rows = $rowLayout->repeat(times: ROWS_PER_PAGE, dy: ROW_PITCH);
$pages = array_chunk($events, ROWS_PER_PAGE);

foreach ($pages as $number => $page) {
    $pdf->page();
    $pdf->writeText(LEFT, 12, 180, sprintf('Event list - page %d of %d', $number + 1, count($pages)), 8, 'bold', 14);
    $pdf->writeText(LEFT, 19, 180, sprintf('%d events', count($events)), 6, null, 9);

    foreach ($page as $index => $event) {
        $pdf->writeAll($rows[$index], $event);
    }

    $pdf->writeText(LEFT, 288, 180, sprintf('%d / %d', $number + 1, count($pages)), 6, null, 8, Align::Center);
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
