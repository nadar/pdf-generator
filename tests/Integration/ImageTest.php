<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Exception\MissingImageException;
use Nadar\PdfGenerator\Fit;
use Nadar\PdfGenerator\ImageBox;
use Nadar\PdfGenerator\Shape;
use Nadar\PdfGenerator\Value\Color;

final class ImageTest extends IntegrationTestCase
{
    public function testImageIsEmbedded(): void
    {
        $png = $this->makePng('photo.png', 400, 300);

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->image(new ImageBox('p', 20, 20, 40, 30), $png);
        $bytes = $pdf->bytes();

        self::assertTrue(self::hasEmbeddedImage($bytes));
    }

    /** A clipped shape must actually emit a clipping path. */
    public function testCircleShapeEmitsAClippingPath(): void
    {
        $png = $this->makePng('photo.png', 300, 300);

        $plain = $this->pdf();
        $plain->page(null, 'A4');
        $plain->image(new ImageBox('p', 20, 20, 30, 30), $png);

        $clipped = $this->pdf();
        $clipped->page(null, 'A4');
        $clipped->image(ImageBox::circle('p', cx: 35, cy: 35, diameter: 30), $png);

        // "W n" is the PDF clip-path operator pair, emitted on its own line
        self::assertDoesNotMatchRegularExpression('/\bW\s+n\b/', $plain->bytes());
        self::assertMatchesRegularExpression('/\bW\s+n\b/', $clipped->bytes());
    }

    public function testRoundedShapeEmitsAClippingPath(): void
    {
        $png = $this->makePng('photo.png', 300, 300);

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->image(ImageBox::rounded('p', 20, 20, 40, 30, radius: 5.0), $png);

        self::assertMatchesRegularExpression('/\bW\s+n\b/', $pdf->bytes());
    }

    /** The designed fallback: a filled shape in the brand colour, not a gap. */
    public function testPlaceholderIsDrawnWhenTheSourceIsMissing(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->image(
            ImageBox::circle('p', cx: 35, cy: 35, diameter: 30, placeholder: Color::hex('#ff920c')),
            $this->workspace . '/does-not-exist.png'
        );
        $bytes = $pdf->bytes();

        self::assertFalse(self::hasEmbeddedImage($bytes), 'no image is embedded');
        // #ff920c as a PDF fill colour: 255/146/12 scaled to 0..1
        self::assertStringContainsString('1.000000 0.572549 0.047059 rg', $bytes);
    }

    public function testNullSourceUsesThePlaceholder(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->image(ImageBox::circle('p', cx: 35, cy: 35, diameter: 30, placeholder: Color::hex('#ff920c')), null);

        self::assertFalse(self::hasEmbeddedImage($pdf->bytes()));
    }

    public function testOnMissingIsCalled(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $called = null;
        $pdf->image(
            new ImageBox('avatar', 10, 10, 30, 30),
            null,
            function (ImageBox $box) use (&$called): void {
                $called = $box->id;
            }
        );

        self::assertSame('avatar', $called);
    }

    /**
     * "Coloured shape with a label on top" is the common designed fallback, so
     * the placeholder must be painted *and* the callback run - not one or the
     * other, which would force the callback to redraw the shape by hand.
     */
    public function testPlaceholderAndOnMissingCompose(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $pdf->image(
            ImageBox::circle('avatar', cx: 35, cy: 35, diameter: 30, placeholder: Color::hex('#ff920c')),
            null,
            function (ImageBox $box) use ($pdf): void {
                $pdf->writeText($box->x, $box->y + $box->h / 2, $box->w, 'Image');
            }
        );
        $bytes = $pdf->bytes();

        self::assertStringContainsString('1.000000 0.572549 0.047059 rg', $bytes, 'the placeholder is filled');
        self::assertContains('Image', self::drawnText($bytes), 'and the callback drew on top of it');
    }

    /** The fill a placeholder performs, reachable without raw(). */
    public function testFillShapeIsPubliclyAvailable(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $result = $pdf->fillShape(ImageBox::circle('badge', cx: 35, cy: 35, diameter: 30), Color::hex('#223764'));

        self::assertSame($pdf, $result);
        self::assertStringContainsString('0.133333 0.215686 0.392157 rg', $pdf->bytes());
    }

    public function testMissingSourceWithoutAFallbackThrowsAndNamesTheBox(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(MissingImageException::class);
        $this->expectExceptionMessageMatches('/box "hero".*does not exist or is not readable/s');
        $pdf->image(new ImageBox('hero', 0, 0, 10, 10), $this->workspace . '/nope.png');
    }

    public function testNullSourceWithoutAFallbackThrows(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(MissingImageException::class);
        $this->expectExceptionMessageMatches('/No image source given for box "hero"/');
        $pdf->image(new ImageBox('hero', 0, 0, 10, 10), null);
    }

    public function testNonImageFileIsRejected(): void
    {
        $path = $this->workspace . '/not-an-image.png';
        file_put_contents($path, 'plain text');

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(MissingImageException::class);
        $this->expectExceptionMessageMatches('/is not a readable image/');
        $pdf->image(new ImageBox('p', 0, 0, 10, 10), $path);
    }

    /** Reading the dimensions must not cost a second read of the source. */
    public function testEachSourceIsResolvedOnlyOnce(): void
    {
        $png = $this->makePng('photo.png', 400, 300);

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        foreach (range(0, 5) as $i) {
            $pdf->image(new ImageBox('p' . $i, 20, 20 + $i * 35, 40, 30), $png);
        }

        self::assertSame(1, $pdf->imageLoader()->cached());
    }

    public function testFailuresAreRememberedRatherThanRetried(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $box = ImageBox::circle('p', cx: 35, cy: 35, diameter: 30, placeholder: Color::black());

        foreach (range(0, 2) as $i) {
            $pdf->image($box->offset(0, $i * 35.0), $this->workspace . '/missing.png');
        }

        self::assertSame(0, $pdf->imageLoader()->cached());
    }

    public function testLoaderCanBeCleared(): void
    {
        $png = $this->makePng('photo.png', 40, 40);

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->image(new ImageBox('p', 20, 20, 20, 20), $png);
        self::assertSame(1, $pdf->imageLoader()->cached());

        $pdf->imageLoader()->clear();
        self::assertSame(0, $pdf->imageLoader()->cached());
    }

    public function testFitAffectsTheDrawnGeometry(): void
    {
        $png = $this->makePng('wide.png', 400, 300);

        $cover = new ImageBox('p', 0, 0, 30, 30, Fit::Cover);
        $contain = new ImageBox('p', 0, 0, 30, 30, Fit::Contain);

        self::assertNotSame($cover->placement(400, 300), $contain->placement(400, 300));

        // both render without error
        foreach ([$cover, $contain] as $box) {
            $pdf = $this->pdf();
            $pdf->page(null, 'A4');
            $pdf->image($box, $png);
            self::assertTrue(self::hasEmbeddedImage($pdf->bytes()));
        }
    }

    /**
     * An unrotated image already carries a scale/translate matrix, so the test
     * is that rotation introduces one with non-zero shear terms.
     */
    public function testRotationEmitsARotationMatrix(): void
    {
        $png = $this->makePng('photo.png', 100, 100);

        $straight = $this->pdf();
        $straight->page(null, 'A4');
        $straight->image(new ImageBox('p', 20, 20, 30, 30, Fit::Cover, Shape::rect()), $png);

        $turned = $this->pdf();
        $turned->page(null, 'A4');
        $turned->image(new ImageBox('p', 20, 20, 30, 30, Fit::Cover, Shape::rect(), rotation: 15.0), $png);

        self::assertFalse(self::hasShearedMatrix($straight->bytes()));
        self::assertTrue(self::hasShearedMatrix($turned->bytes()));
    }

    /** Whether any `cm` matrix in the document has non-zero b/c components. */
    private static function hasShearedMatrix(string $bytes): bool
    {
        preg_match_all('/(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) cm/', $bytes, $matches, PREG_SET_ORDER);

        foreach ($matches as $matrix) {
            if (abs((float) $matrix[2]) > 0.0001 || abs((float) $matrix[3]) > 0.0001) {
                return true;
            }
        }

        return false;
    }
}
