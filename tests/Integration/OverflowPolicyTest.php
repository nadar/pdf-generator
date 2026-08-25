<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Exception\OverflowException;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\TextBox;

final class OverflowPolicyTest extends IntegrationTestCase
{
    private const LONG = 'A remarkably long event title that will never fit on a single line';

    public function testShrinkReducesTheSizeUntilItFits(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $box = new TextBox('t', 20, 20, 120, 20.0, size: 24.0, overflow: Overflow::Shrink);
        $metrics = $pdf->probe($box, self::LONG);

        self::assertLessThan(24.0, $metrics->size);
        self::assertGreaterThanOrEqual($box->minSizeFor(24.0), $metrics->size);
        self::assertTrue($metrics->fits);
    }

    /** Documented behaviour: Shrink throws rather than overflowing silently. */
    public function testShrinkThrowsWhenEvenTheFloorIsTooLarge(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessageMatches('/Unable to fit text in box "t".*ShrinkThenClip/s');
        $pdf->write(new TextBox('t', 20, 20, 40, 4.0, size: 24.0, overflow: Overflow::Shrink), self::LONG);
    }

    /** The safe policy for data-driven slots: always renders, never throws. */
    public function testShrinkThenClipNeverThrows(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $pdf->write(new TextBox('t', 20, 20, 40, 4.0, size: 24.0, overflow: Overflow::ShrinkThenClip), self::LONG);
        $metrics = $pdf->lastMetrics();

        self::assertNotNull($metrics);
        self::assertSame(Overflow::Clip, $metrics->overflow, 'falls back to clipping');
        self::assertNotEmpty(self::drawnText($pdf->bytes()));
    }

    public function testTruncateShortensTheTextAndAddsAnEllipsis(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->write(new TextBox('t', 20, 20, 40, 8.0, size: 12.0, overflow: Overflow::Truncate), self::LONG);

        $drawn = implode(' ', self::drawnText($pdf->bytes()));

        self::assertStringContainsString('...', $drawn);
        self::assertStringNotContainsString('single line', $drawn);
    }

    /**
     * A height alone cannot express "one line, whatever size it takes"; maxLines
     * can. Shrinking is tried first, and surplus words are dropped only when the
     * floor is reached - clipping there would leave a sliver of the next line.
     */
    public function testMaxLinesIsEnforced(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $uncapped = $pdf->probe(
            new TextBox('a', 20, 20, 120, 11.5, size: 24.0, overflow: Overflow::ShrinkThenClip),
            self::LONG
        );
        $capped = $pdf->probe(
            new TextBox('b', 20, 40, 120, 11.5, size: 24.0, overflow: Overflow::ShrinkThenClip, maxLines: 1),
            self::LONG
        );

        self::assertSame(2, $uncapped->lines);
        self::assertSame(1, $capped->lines);
    }

    /** With a low enough floor the text shrinks to one full line instead of losing words. */
    public function testMaxLinesPrefersShrinkingOverDroppingWords(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $box = new TextBox('b', 20, 20, 120, 11.5, size: 24.0, minSize: 8.0, overflow: Overflow::ShrinkThenClip, maxLines: 1);
        $metrics = $pdf->probe($box, self::LONG);
        $pdf->write($box, self::LONG);

        self::assertSame(1, $metrics->lines);
        self::assertLessThan(24.0, $metrics->size);
        self::assertGreaterThanOrEqual(8.0, $metrics->size);
        self::assertStringNotContainsString('...', implode(' ', self::drawnText($pdf->bytes())));
    }

    public function testShrinkWithUnreachableMaxLinesExplainsTheLineCap(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessageMatches('/could not reach 1 line\(s\).*or maxLines/s');
        $pdf->write(
            new TextBox('t', 20, 20, 120, 11.5, size: 24.0, overflow: Overflow::Shrink, maxLines: 1),
            self::LONG
        );
    }

    /** A policy on the box with nothing to constrain it is a configuration mistake. */
    public function testPolicyOnTheBoxWithoutHeightIsRejected(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessageMatches('/declares overflow policy Shrink but no height/');
        $pdf->write(new TextBox('t', 20, 20, 120, overflow: Overflow::Shrink), 'x');
    }

    /**
     * The settings' default must not turn every heightless box into an error -
     * it exists so height-constrained slots need not repeat it.
     */
    public function testSettingsDefaultDoesNotRequireEveryBoxToHaveAHeight(): void
    {
        $pdf = $this->pdf(overflow: Overflow::ShrinkThenClip);
        $pdf->page(null, 'A4');

        $pdf->write(new TextBox('headline', 20, 20, 170, size: 35.0), 'Event Highlights');
        $metrics = $pdf->lastMetrics();

        self::assertNotNull($metrics);
        self::assertSame(Overflow::None, $metrics->overflow);
        self::assertEqualsWithDelta(35.0, $metrics->size, 0.001, 'nothing constrains it, so nothing shrinks');
    }

    public function testSettingsSupplyTheDefaultPolicy(): void
    {
        $pdf = $this->pdf(overflow: Overflow::ShrinkThenClip);
        $pdf->page(null, 'A4');

        // no policy on the box, so the settings' ShrinkThenClip applies
        $metrics = $pdf->probe(new TextBox('t', 20, 20, 120, 11.5, size: 24.0), self::LONG);

        self::assertLessThan(24.0, $metrics->size);
    }

    public function testNoneLeavesTheTextOverflowing(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $metrics = $pdf->probe(new TextBox('t', 20, 20, 120, 5.0, size: 24.0, overflow: Overflow::None), self::LONG);

        self::assertSame(24.0, $metrics->size);
        self::assertFalse($metrics->fits);
    }
}
