<?php

declare(strict_types=1);

/**
 * Template overlay poster: the shape of job this package exists for.
 *
 * A designer-exported PDF is stamped as the background; on top go a month
 * headline and repeated event rows, each with a cover-cropped circular image, a
 * shrink-to-fit title, a meta line and a QR code linking back to the event.
 * More rows than fit on a page continue on the next one.
 *
 * Two things keep this runnable straight after `composer install`:
 *
 *   - it generates its own stand-in template on the first pass, so the
 *     repository needs no binary template asset - and that doubles as a smoke
 *     test that page(null, 'A4') -> save() -> page('template.pdf') round-trips;
 *   - it falls back to TCPDF's core fonts when no brand fonts are compiled.
 *
 * To run it with real fonts, drop Inter-Regular.ttf, Inter-Bold.ttf and
 * Inter-Medium.ttf into examples/assets/fonts and build the cache:
 *
 *   vendor/bin/pdf-generator fonts:build \
 *       --fonts=examples/assets/fonts --cache=examples/assets/fonts/cache
 *
 * Usage: php examples/template-overlay-poster.php
 */

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Anchor;
use Nadar\PdfGenerator\EccLevel;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\ImageBox;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\Support\FontCompiler;
use Nadar\PdfGenerator\TextBox;
use Nadar\PdfGenerator\Value\Color;

require dirname(__DIR__) . '/vendor/autoload.php';

const BRAND = '#223764';
const ACCENT = '#ff920c';
const WAVE = [20, 127, 187];

final class PosterSettings extends AbstractPdfSettings
{
    public function __construct(private readonly bool $brandFonts)
    {
    }

    /** Whether the brand faces have been compiled into the cache. */
    public static function brandFontsAvailable(): bool
    {
        foreach (['inter', 'interb', 'intermedium'] as $key) {
            if (!is_file(FontCompiler::cacheFile(__DIR__ . '/assets/fonts/cache', $key))) {
                return false;
            }
        }

        return true;
    }

    public function fontPath(): string
    {
        return __DIR__ . '/assets/fonts';
    }

    public function fontCachePath(): string
    {
        // A trailing separator is optional: the package normalises it, which the
        // raw TCPDF API does not.
        return __DIR__ . '/assets/fonts/cache';
    }

    public function templatePath(): string
    {
        // In a real project this points at the committed print PDFs.
        return __DIR__ . '/output';
    }

    public function fonts(): FontSet
    {
        if (!$this->brandFonts) {
            // Core fonts need no compilation, so the example still runs - and
            // still gets a real bold face rather than a synthetic one.
            return FontSet::make()
                ->coreFamily('helvetica')
                ->role('regular', 'helvetica')
                ->role('bold', 'helvetica', 'bold')
                ->role('medium', 'helvetica', 'bold');
        }

        return FontSet::make()
            // regular and bold are styles of one family, as TCPDF models them
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
            // any further weight a brand kit ships is registered by name
            ->face('inter', 'medium', 'Inter-Medium.ttf')
            ->role('regular', 'inter')
            ->role('bold', 'inter', 'bold')
            ->role('medium', 'inter', 'medium');
    }

    public function textColor(): Color
    {
        return Color::hex(BRAND);
    }

    public function fontSize(): float
    {
        return 12.0;
    }

    /**
     * The safe default for data-driven slots: a single unusually long title
     * shrinks and clips instead of turning into a 500.
     */
    public function overflow(): Overflow
    {
        return Overflow::ShrinkThenClip;
    }
}

/**
 * Stands in for the designer template, which is normally an exported print PDF.
 */
function buildTemplate(PosterSettings $settings, string $path): void
{
    $pdf = (new PdfGenerator($settings))->title('Poster template');
    $pdf->page(null, 'A4');

    // Background artwork is exactly the kind of one-off drawing raw() is for.
    $raw = $pdf->raw();
    $raw->SetFillColorArray(WAVE);
    $raw->Rect(0, 245, 210, 52, 'F');
    $raw->Ellipse(150, 245, 90, 22, 0, 0, 360, 'F');

    $pdf->write(
        new TextBox('headline', x: 20, y: 20.0, w: 170, font: 'medium', size: 35, align: Align::Center, anchor: Anchor::Baseline),
        'Event Highlights'
    );

    $pdf->save($path);
}

final class Poster
{
    private const TEMPLATE = 'poster-template.pdf';

    private const ROWS_PER_PAGE = 6;

    /** Measured once off the design; every row is this pitch below the previous. */
    private const ROW_PITCH = 40.3;

    private const IMAGE_CX = 30.34;

    private const IMAGE_CY = 68.3;

    private const IMAGE_DIAMETER = 30.85;

    private const QR_X = 179.0;

    private const QR_Y = 58.63;

    private const QR_SIZE = 19.5;

    /** The text geometry of one row, as measured off the reference. */
    private readonly Layout $row;

    public function __construct(private readonly PdfGenerator $pdf)
    {
        $this->row = Layout::fromArray([
            [
                'id' => 'title',
                'x' => 53.2, 'y' => 58.48, 'w' => 120.0, 'h' => 11.5,
                'font' => 'bold', 'size' => 24,
                // one line at whatever size fits, which a height alone cannot express
                'maxLines' => 1, 'minSize' => 13.0,
            ],
            [
                'id' => 'meta',
                'x' => 53.2, 'y' => 70.11, 'w' => 120.0, 'h' => 9.5,
                'font' => 'bold', 'size' => 19,
                'maxLines' => 1, 'minSize' => 11.0,
            ],
        ]);
    }

    /** @param list<array{title:string,meta:string,image:?string,url:?string}> $events */
    public function render(string $month, array $events): string
    {
        $rows = $this->row->repeat(times: self::ROWS_PER_PAGE, dy: self::ROW_PITCH);

        foreach (array_chunk($events, self::ROWS_PER_PAGE) ?: [[]] as $page) {
            $this->pdf->page(self::TEMPLATE);
            $this->pdf->write(
                new TextBox('month', x: 20, y: 28.35, w: 170, font: 'medium', size: 25, align: Align::Center),
                $month
            );

            foreach ($page as $index => $event) {
                $offset = $index * self::ROW_PITCH;

                $this->pdf->writeAll($rows[$index], $event);
                $this->image($event['image'], self::IMAGE_CY + $offset);

                if ($event['url'] !== null) {
                    // Transparent background and no quiet zone are the defaults,
                    // so the code sits on the artwork instead of punching a white
                    // tile through it, at exactly the measured size.
                    $this->pdf->qr(
                        $event['url'],
                        x: self::QR_X,
                        y: self::QR_Y + $offset,
                        size: self::QR_SIZE,
                        color: Color::hex(BRAND),
                        level: EccLevel::M,
                    );
                }
            }
        }

        return $this->pdf->bytes();
    }

    /**
     * Cover-crops the image into a circle.
     *
     * The placeholder is a designed state, not an accident: a CDN image failing
     * in production renders the same accent circle the design specifies. If the
     * network is unavailable when you run this, that is the path you will see.
     */
    private function image(?string $source, float $centerY): void
    {
        $this->pdf->image(
            ImageBox::circle(
                'photo',
                cx: self::IMAGE_CX,
                cy: $centerY,
                diameter: self::IMAGE_DIAMETER,
                placeholder: Color::hex(ACCENT),
            ),
            $source,
            // Label the placeholder, mirroring the "Bild" circle in the design.
            fn (ImageBox $box) => $this->placeholder($box),
        );
    }

    private function placeholder(ImageBox $box): void
    {
        $this->pdf->raw()->Ellipse(
            ...[...$box->bounds()->center(), $box->w / 2, $box->h / 2, 0, 0, 360, 'F', [], Color::hex(ACCENT)->toArray()]
        );

        $this->pdf->write(
            new TextBox(
                'image_label',
                x: $box->x,
                y: $box->y + $box->h / 2,
                w: $box->w,
                font: 'regular',
                size: 16,
                color: Color::white(),
                align: Align::Center,
                anchor: Anchor::Baseline,
            ),
            'Image'
        );
    }
}

$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

$brandFonts = PosterSettings::brandFontsAvailable();
if (!$brandFonts) {
    fwrite(STDOUT, "No compiled brand fonts found; falling back to TCPDF core fonts.\n");
}

$settings = new PosterSettings($brandFonts);
buildTemplate($settings, $outputDir . '/poster-template.pdf');

/*
 * Seven events deliberately overflow onto a second page. Row 3 is the
 * long-title case, so the example shows what shrink-to-one-line does; row 1
 * points at a remote image, the rest fall back to the designed placeholder.
 */
$events = [
    ['title' => 'City Run', 'meta' => '1 June - Market Square', 'image' => 'https://picsum.photos/seed/poster/400/400', 'url' => 'https://example.com/events/city-run'],
    ['title' => 'Open Air Cinema', 'meta' => '5 June - Riverside Park', 'image' => null, 'url' => 'https://example.com/events/open-air-cinema'],
    ['title' => 'A remarkably long event title that will never fit on a single line', 'meta' => '9 June - Town Hall', 'image' => null, 'url' => 'https://example.com/events/long-title'],
    ['title' => 'Museum Night', 'meta' => '14 June - History Museum', 'image' => null, 'url' => 'https://example.com/events/museum-night'],
    ['title' => 'Farmers Market', 'meta' => '20 June - Old Town', 'image' => null, 'url' => null],
    ['title' => 'Summer Concert', 'meta' => '25 June - Bandstand', 'image' => null, 'url' => 'https://example.com/events/summer-concert'],
    ['title' => 'Guided City Walk', 'meta' => '28 June - Tourist Office', 'image' => null, 'url' => 'https://example.com/events/city-walk'],
];

$pdf = new PdfGenerator($settings);
$poster = new Poster($pdf);

$output = $outputDir . '/poster.pdf';
if (file_put_contents($output, $poster->render('June', $events)) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

printf("Created %s (%d pages)\n", $output, (int) ceil(count($events) / 6));
