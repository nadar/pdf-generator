<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Barcode1D;
use Nadar\PdfGenerator\EccLevel;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\PdfGenerator;
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
        $pdf->qr(self::URL, x: 179, y: 58.6, size: 19.5);

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

        $transparent = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20));
        $filled = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20, background: $background));

        self::assertNotSame($transparent, $filled);
        self::assertStringContainsString($fill, $filled, 'an opaque background is painted');
        self::assertStringNotContainsString($fill, $transparent, 'nothing is painted behind the modules');
    }

    public function testModuleColourIsApplied(): void
    {
        $branded = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20, color: Color::hex('#223764')));

        // 34/55/100 scaled to 0..1
        self::assertStringContainsString('0.133333 0.215686 0.392157 rg', $branded);
    }

    public function testQuietZoneChangesTheModuleSize(): void
    {
        $tight = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20));
        $padded = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20, quietZone: 4));

        self::assertNotSame($tight, $padded);
    }

    public function testErrorCorrectionLevelChangesTheOutput(): void
    {
        $m = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20, level: EccLevel::M));
        $h = $this->render(static fn (PdfGenerator $pdf) => $pdf->qr(self::URL, 20, 20, 20, level: EccLevel::H));

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

        $pdf->qr(self::URL, 100, 100, 20);
        self::assertEqualsWithDelta(11.0, $pdf->raw()->GetX(), 0.0001);
        self::assertEqualsWithDelta(22.0, $pdf->raw()->GetY(), 0.0001);

        $pdf->barcode1d('4006381333931', Barcode1D::Ean13, 20, 200, 50, 15);
        self::assertEqualsWithDelta(11.0, $pdf->raw()->GetX(), 0.0001);
        self::assertEqualsWithDelta(22.0, $pdf->raw()->GetY(), 0.0001);
    }

    /** Text placed after a code must land where it was asked to, not after it. */
    public function testTextAfterACodeStillLandsAtItsOwnCoordinates(): void
    {
        $withCode = $this->pdf();
        $withCode->page(null, 'A4');
        $withCode->qr(self::URL, 150, 20, 20);
        $withCode->writeText(20, 100, 100, 'after');

        $withoutCode = $this->pdf();
        $withoutCode->page(null, 'A4');
        $withoutCode->writeText(20, 100, 100, 'after');

        self::assertSame(
            self::baselines($withoutCode->bytes()),
            self::baselines($withCode->bytes())
        );
    }

    public function testBarcode1dDraws(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->barcode1d('4006381333931', Barcode1D::Ean13, 20, 20, 50, 15);

        self::assertStringContainsString(' re f', $pdf->bytes());
    }

    public function testBarcode1dCanPrintItsLabel(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->barcode1d('4006381333931', Barcode1D::Ean13, 20, 20, 50, 15, showText: true);

        self::assertStringContainsString('4006381333931', implode(' ', self::drawnText($pdf->bytes())));
    }

    public function testEmptyDataIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/empty data/');
        $pdf->qr('   ', 20, 20, 20);
    }

    public function testNonPositiveSizeIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/QR size must be positive/');
        $pdf->qr(self::URL, 20, 20, 0);
    }

    public function testNegativeQuietZoneIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/quiet zone must not be negative/');
        $pdf->qr(self::URL, 20, 20, 20, quietZone: -1);
    }

    public function testBarcodeRejectsEmptyDataAndBadDimensions(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        try {
            $pdf->barcode1d('', Barcode1D::Code128, 20, 20, 50, 15);
            self::fail('empty barcode data should be rejected');
        } catch (ConfigurationException $exception) {
            self::assertStringContainsString('empty data', $exception->getMessage());
        }

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Barcode size must be positive/');
        $pdf->barcode1d('123', Barcode1D::Code128, 20, 20, 0, 15);
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
