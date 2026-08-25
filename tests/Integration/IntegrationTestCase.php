<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\Tests\Support\TestSettings;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    /** Points per millimetre. */
    protected const K = 72 / 25.4;

    protected const A4_HEIGHT_MM = 297.0;

    protected string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/pdfgen-it-' . bin2hex(random_bytes(6));
        mkdir($this->workspace, 0o777, true);
    }

    protected function tearDown(): void
    {
        self::removeDir($this->workspace);
    }

    protected function settings(
        ?FontSet $fonts = null,
        Overflow $overflow = Overflow::None,
        float $ratio = 1.25,
        bool $debug = false
    ): TestSettings {
        return new TestSettings($this->workspace, $fonts ?? new FontSet(), $overflow, $ratio, $debug);
    }

    /** A generator with compression disabled, so content streams stay readable. */
    protected function pdf(
        ?FontSet $fonts = null,
        Overflow $overflow = Overflow::None,
        float $ratio = 1.25,
        bool $debug = false
    ): PdfGenerator {
        $pdf = new PdfGenerator($this->settings($fonts, $overflow, $ratio, $debug));
        $pdf->raw()->SetCompression(false);

        return $pdf;
    }

    /**
     * TCPDF's LGPL attribution, which `TCPDF::Close()` writes at 1pt into the
     * bottom edge of the last page of every document.
     *
     * It is not a defect and not something this package adds; the string is
     * hex-obfuscated in TCPDF's source, which is why it cannot be grepped for.
     * Suppressing it means overriding the protected `$tcpdflink` in a TCPDF
     * subclass - a licensing decision, so it is left to the consumer.
     */
    protected const TCPDF_ATTRIBUTION = 'Powered by TCPDF';

    /**
     * Every text-showing operation in the document, in stream order.
     *
     * TCPDF emits `BT <x> <y> Td [(text)] TJ`, with the position in PDF user
     * space, so this reads what was *actually* rendered rather than trusting the
     * library's own arithmetic. Coordinates come back in mm from the top-left.
     *
     * TCPDF's own attribution line and empty writes are excluded: an empty slot
     * still emits an operation, which is not "text that was drawn".
     *
     * Requires compression to be off - see {@see pdf()}.
     *
     * @return list<array{x:float,y:float,text:string}>
     */
    protected static function textOps(string $pdfBytes, float $pageHeight = self::A4_HEIGHT_MM): array
    {
        $pattern = '/BT\s+(-?[\d.]+)\s+(-?[\d.]+)\s+Td\s*\[\((.*?)\)\]\s*TJ/s';

        if (!preg_match_all($pattern, $pdfBytes, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $ops = [];
        foreach ($matches as $set) {
            $text = $set[3];

            // An empty slot still emits an operation (TCPDF writes a single
            // space), which is not "text that was drawn".
            if (trim($text) === '' || str_contains($text, self::TCPDF_ATTRIBUTION)) {
                continue;
            }

            $ops[] = [
                'x' => ((float) $set[1]) / self::K,
                'y' => $pageHeight - ((float) $set[2]) / self::K,
                'text' => $text,
            ];
        }

        return $ops;
    }

    /**
     * Baseline y positions (mm from the page top) of every text operation.
     *
     * @return list<float>
     */
    protected static function baselines(string $pdfBytes, float $pageHeight = self::A4_HEIGHT_MM): array
    {
        return array_map(
            static fn (array $op): float => $op['y'],
            self::textOps($pdfBytes, $pageHeight)
        );
    }

    /**
     * Left x positions (mm from the page edge) of every text operation.
     *
     * @return list<float>
     */
    protected static function textLeftEdges(string $pdfBytes): array
    {
        return array_map(
            static fn (array $op): float => $op['x'],
            self::textOps($pdfBytes)
        );
    }

    /**
     * The text payloads TCPDF actually drew.
     *
     * @return list<string>
     */
    protected static function drawnText(string $pdfBytes): array
    {
        return array_map(
            static fn (array $op): string => $op['text'],
            self::textOps($pdfBytes)
        );
    }

    /**
     * Whether the document embeds at least one image XObject.
     *
     * Checking for "/Image" alone would always match: every PDF declares
     * `/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]` whether or not it has
     * images.
     */
    protected static function hasEmbeddedImage(string $pdfBytes): bool
    {
        return str_contains($pdfBytes, '/Subtype /Image');
    }

    /** Write a tiny PNG of the given pixel size and return its path. */
    protected function makePng(string $name, int $width, int $height): string
    {
        self::assertGreaterThan(0, $width);
        self::assertGreaterThan(0, $height);

        $path = $this->workspace . '/' . $name;
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        $color = imagecolorallocate($image, 30, 120, 200);
        self::assertNotFalse($color);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /** Render a blank single-page PDF into the workspace, for use as a template. */
    protected function makeTemplate(string $name, string $format = 'A4'): string
    {
        $pdf = new PdfGenerator($this->settings());
        // uncompressed, so the stamped content stays inspectable once imported
        $pdf->raw()->SetCompression(false);
        $pdf->page(null, $format);
        $pdf->writeText(20, 20, 100, 'template background');

        return $pdf->save($this->workspace . '/' . $name);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? self::removeDir($file) : unlink($file);
        }
        @rmdir($dir);
    }
}
