<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Barcode1D;
use Nadar\PdfGenerator\BarcodeBox;
use Nadar\PdfGenerator\EccLevel;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Exception\InvalidValueException;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\QrBox;
use Nadar\PdfGenerator\Value\Color;

final class CodeTest extends IntegrationTestCase
{
    private const URL = 'https://example.com/events/city-run';

    public function testQrDrawsModules(): void
    {
        $blank = $this->pdf();
        $blank->page(null, 'A4');
        $blankSize = strlen($blank->bytes());

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->qrAt(self::URL, x: 179, y: 58.6, size: 19.5);

        self::assertGreaterThan($blankSize, strlen($pdf->bytes()));
    }

    /**
     * The default that makes a code sit on artwork instead of punching a white
     * tile through it. TCPDF's own default is transparent too, but the point of
     * the wrapper is that this is guaranteed rather than incidental.
     */
    public function testBackgroundIsTransparentByDefault(): void
    {
        // a distinctive background, so its fill operator is unmistakable
        $background = Color::hex('#ffcc00');
        $fill = '1.000000 0.800000 0.000000 rg';

        $transparent = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20));
        $filled = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20, background: $background));

        self::assertNotSame($transparent, $filled);
        self::assertStringContainsString($fill, $filled, 'an opaque background is painted');
        self::assertStringNotContainsString($fill, $transparent, 'nothing is painted behind the modules');
    }

    public function testModuleColourIsApplied(): void
    {
        $branded = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20, color: Color::hex('#223764')));

        // 34/55/100 scaled to 0..1
        self::assertStringContainsString('0.133333 0.215686 0.392157 rg', $branded);
    }

    public function testQuietZoneChangesTheModuleSize(): void
    {
        $tight = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20));
        $padded = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20, quietZone: 4));

        self::assertNotSame($tight, $padded);
    }

    public function testErrorCorrectionLevelChangesTheOutput(): void
    {
        $m = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20, level: EccLevel::M));
        $h = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20, level: EccLevel::H));

        self::assertNotSame($m, $h);
        // a higher level packs more modules into the same box
        self::assertGreaterThan(strlen($m), strlen($h));
    }

    /** A code must not move the cursor and disturb the next absolute write. */
    public function testCursorIsPreservedAcrossCodes(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->raw()->SetXY(11.0, 22.0);

        $pdf->qrAt(self::URL, 100, 100, 20);
        self::assertEqualsWithDelta(11.0, $pdf->raw()->GetX(), 0.0001);
        self::assertEqualsWithDelta(22.0, $pdf->raw()->GetY(), 0.0001);

        $pdf->barcode1dAt('4006381333931', Barcode1D::Ean13, 20, 200, 50, 15);
        self::assertEqualsWithDelta(11.0, $pdf->raw()->GetX(), 0.0001);
        self::assertEqualsWithDelta(22.0, $pdf->raw()->GetY(), 0.0001);
    }

    /** Text placed after a code must land where it was asked to, not after it. */
    public function testTextAfterACodeStillLandsAtItsOwnCoordinates(): void
    {
        $withCode = $this->pdf();
        $withCode->page(null, 'A4');
        $withCode->qrAt(self::URL, 150, 20, 20);
        $withCode->writeText(20, 100, 100, 'after');

        $withoutCode = $this->pdf();
        $withoutCode->page(null, 'A4');
        $withoutCode->writeText(20, 100, 100, 'after');

        self::assertSame(
            self::baselines($withoutCode->bytes()),
            self::baselines($withCode->bytes())
        );
    }

    /**
     * The regression that made deterministic() a half-truth: TCPDF picks the QR
     * mask from two randomly chosen candidates unless QR_FIND_FROM_RANDOM is
     * false, so a document with a code rendered differently every time.
     */
    public function testDocumentsContainingQrCodesAreByteStable(): void
    {
        $render = function (): string {
            $pdf = $this->pdf();
            $pdf->deterministic(1_700_000_000)->page(null, 'A4');

            foreach (range(0, 5) as $index) {
                $pdf->qrAt('https://example.com/events/' . $index, 179, 20 + $index * 40, 19.5);
            }

            return $pdf->bytes();
        };

        self::assertSame(md5($render()), md5($render()));
        self::assertSame(md5($render()), md5($render()));
    }

    /**
     * The same payload must always produce the same module pattern.
     *
     * Compares the drawn rectangles rather than whole documents, so the
     * assertion is about the QR itself and not about document metadata.
     */
    public function testTheSamePayloadProducesTheSamePattern(): void
    {
        $modules = fn (): array => self::moduleRects(
            $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20))
        );

        $first = $modules();

        self::assertNotEmpty($first);
        self::assertSame($first, $modules());
        self::assertSame($first, $modules());
    }

    /**
     * A different payload must still give a different pattern - otherwise the
     * test above would pass on a renderer that draws nothing.
     */
    public function testDifferentPayloadsProduceDifferentPatterns(): void
    {
        $a = self::moduleRects($this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL, 20, 20, 20)));
        $b = self::moduleRects($this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(self::URL . '/other', 20, 20, 20)));

        self::assertNotSame($a, $b);
    }

    /**
     * The filled rectangles in a content stream - one per dark barcode module.
     *
     * @return list<string>
     */
    private static function moduleRects(string $pdfBytes): array
    {
        preg_match_all('/(-?[\d.]+ -?[\d.]+ -?[\d.]+ -?[\d.]+) re f/', $pdfBytes, $matches);

        return $matches[1];
    }

    /** A code declared as a slot renders identically to the coordinate form. */
    public function testSlotFormMatchesTheCoordinateForm(): void
    {
        $viaSlot = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(
            new QrBox('link', x: 20, y: 20, size: 20, color: Color::hex('#223764')),
            self::URL
        ));
        $viaCoordinates = $this->render(static fn (PdfGenerator $pdf) => $pdf->qrAt(
            self::URL,
            x: 20,
            y: 20,
            size: 20,
            color: Color::hex('#223764')
        ));

        self::assertSame(self::moduleRects($viaSlot), self::moduleRects($viaCoordinates));
    }

    /** A slot carries its own geometry, so offsetting it moves the code. */
    public function testOffsettingASlotMovesTheCode(): void
    {
        $box = new QrBox('link', x: 20, y: 20, size: 20);

        $atOrigin = self::moduleRects($this->render(static fn (PdfGenerator $pdf) => $pdf->qr($box, self::URL)));
        $shifted = self::moduleRects($this->render(
            static fn (PdfGenerator $pdf) => $pdf->qr($box->offset(0, 40.3), self::URL)
        ));

        self::assertSame(count($atOrigin), count($shifted), 'the same pattern, in a different place');
        self::assertNotSame($atOrigin, $shifted);
    }

    public function testBarcodeSlotForm(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->barcode1d(
            new BarcodeBox('ean', Barcode1D::Ean13, x: 20, y: 20, w: 50, h: 15, showText: true),
            '4006381333931'
        );

        self::assertStringContainsString('4006381333931', implode(' ', self::drawnText($pdf->bytes())));
    }

    public function testSlotsRejectImpossibleGeometryAtConstruction(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessageMatches('/QrBox "link" needs a positive size/');
        new QrBox('link', x: 20, y: 20, size: 0);
    }

    public function testBarcode1dDraws(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->barcode1dAt('4006381333931', Barcode1D::Ean13, 20, 20, 50, 15);

        self::assertStringContainsString(' re f', $pdf->bytes());
    }

    public function testBarcode1dCanPrintItsLabel(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->barcode1dAt('4006381333931', Barcode1D::Ean13, 20, 20, 50, 15, showText: true);

        self::assertStringContainsString('4006381333931', implode(' ', self::drawnText($pdf->bytes())));
    }

    public function testEmptyDataIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/empty data/');
        $pdf->qrAt('   ', 20, 20, 20);
    }

    /** Impossible geometry is rejected where the slot is declared. */
    public function testNonPositiveSizeIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessageMatches('/needs a positive size/');
        $pdf->qrAt(self::URL, 20, 20, 0);
    }

    public function testNegativeQuietZoneIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessageMatches('/quiet zone must not be negative/');
        $pdf->qrAt(self::URL, 20, 20, 20, quietZone: -1);
    }

    public function testBarcodeRejectsEmptyDataAndBadDimensions(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        try {
            $pdf->barcode1dAt('', Barcode1D::Code128, 20, 20, 50, 15);
            self::fail('empty barcode data should be rejected');
        } catch (ConfigurationException $exception) {
            self::assertStringContainsString('empty data', $exception->getMessage());
        }

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessageMatches('/needs positive dimensions/');
        $pdf->barcode1dAt('123', Barcode1D::Code128, 20, 20, 0, 15);
    }

    /** @param callable(PdfGenerator):mixed $draw */
    private function render(callable $draw): string
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $draw($pdf);

        return $pdf->bytes();
    }
}
