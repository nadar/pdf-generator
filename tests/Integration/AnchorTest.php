<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Anchor;
use Nadar\PdfGenerator\TextBox;

/**
 * The vertical-placement invariant, verified against the rendered content stream.
 *
 * With `cellHeight = size(mm) * cellHeightRatio`, the first baseline of a box
 * drawn at cell-top `y` sits at `y + (cellHeight - ascent - descent) / 2 + ascent`.
 * These tests assert that relation holds in the actual output, not just in the
 * library's own metrics.
 */
final class AnchorTest extends IntegrationTestCase
{
    /** Tolerance in mm; the PDF stream stores coordinates with limited precision. */
    private const TOLERANCE = 0.001;

    /**
     * The headline promise of `Anchor::Baseline`: a baseline measured off a
     * design can be used verbatim, with no correction.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('baselineCases')]
    public function testBaselineAnchorPutsTheBaselineExactlyAtY(float $y, float $size, float $ratio): void
    {
        $pdf = $this->pdf(ratio: $ratio);
        $pdf->page(null, 'A4');
        $pdf->write(new TextBox('t', 20, $y, 150, size: $size, anchor: Anchor::Baseline), 'Hxy');

        $baselines = self::baselines($pdf->bytes());

        self::assertNotEmpty($baselines);
        self::assertEqualsWithDelta($y, $baselines[0], self::TOLERANCE);
    }

    /** @return iterable<string,array{0:float,1:float,2:float}> */
    public static function baselineCases(): iterable
    {
        yield 'headline size' => [60.0, 35.0, 1.25];
        yield 'body size' => [100.0, 9.0, 1.25];
        yield 'tight leading' => [42.5, 24.0, 1.0];
        yield 'loose leading' => [180.25, 19.0, 1.6];
    }

    /** Anchor::Top keeps TCPDF's native behaviour: y is the cell top. */
    public function testTopAnchorIsUnchangedAndOffsetFromTheBaseline(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $box = new TextBox('t', 20, 60.0, 150, size: 24.0);
        $probe = $pdf->probe($box, 'Hxy');
        $pdf->write($box, 'Hxy');

        $actual = self::baselines($pdf->bytes())[0];

        // the baseline sits below the declared y, by half-leading plus the ascent
        self::assertGreaterThan(60.0, $actual);
        self::assertEqualsWithDelta($probe->baseline, $actual, self::TOLERANCE);
        self::assertEqualsWithDelta(
            60.0 + ($probe->lineHeight - $probe->ascent - $probe->descent) / 2 + $probe->ascent,
            $actual,
            self::TOLERANCE
        );
    }

    /** CapHeight anchors the top of the capitals, which is what ink-top measurements give. */
    public function testCapHeightAnchorPlacesTheCapitalsAtY(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $box = new TextBox('t', 20, 60.0, 150, size: 24.0, anchor: Anchor::CapHeight);
        $probe = $pdf->probe($box, 'HXY');
        $pdf->write($box, 'HXY');

        $actual = self::baselines($pdf->bytes())[0];

        self::assertEqualsWithDelta($probe->baseline, $actual, self::TOLERANCE);
        // the baseline is one cap height below the requested ink top
        self::assertGreaterThan(60.0, $actual);
        self::assertLessThan(60.0 + $probe->ascent, $actual);
    }

    /** The three anchors must genuinely differ, or the option is decorative. */
    public function testTheThreeAnchorsProduceThreeDistinctPositions(): void
    {
        $positions = [];

        foreach ([Anchor::Top, Anchor::Baseline, Anchor::CapHeight] as $anchor) {
            $pdf = $this->pdf();
            $pdf->page(null, 'A4');
            $pdf->write(new TextBox('t', 20, 60.0, 150, size: 24.0, anchor: $anchor), 'Hxy');
            $positions[$anchor->name] = self::baselines($pdf->bytes())[0];
        }

        self::assertEqualsWithDelta(60.0, $positions['Baseline'], self::TOLERANCE);
        self::assertGreaterThan($positions['CapHeight'], $positions['Top']);
        self::assertGreaterThan($positions['Baseline'], $positions['CapHeight']);
    }

    /** cellHeightRatio participates in placement - worth pinning down explicitly. */
    public function testCellHeightRatioMovesTheBaselineForTopAnchoredBoxes(): void
    {
        $seen = [];

        foreach ([1.0, 1.25, 1.6] as $ratio) {
            $pdf = $this->pdf(ratio: $ratio);
            $pdf->page(null, 'A4');
            $pdf->write(new TextBox('t', 20, 60.0, 150, size: 24.0), 'Hxy');
            $seen[(string) $ratio] = self::baselines($pdf->bytes())[0];
        }

        self::assertGreaterThan($seen['1'], $seen['1.25']);
        self::assertGreaterThan($seen['1.25'], $seen['1.6']);
    }

    /** Later lines advance by exactly one line height. */
    public function testSubsequentLinesAdvanceByTheLineHeight(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $box = new TextBox('t', 20, 60.0, 150, size: 12.0);
        $probe = $pdf->probe($box, "first\nsecond\nthird");
        $pdf->write($box, "first\nsecond\nthird");

        $baselines = self::baselines($pdf->bytes());

        self::assertCount(3, $baselines);
        self::assertSame(3, $probe->lines);
        self::assertEqualsWithDelta($probe->lineHeight, $baselines[1] - $baselines[0], self::TOLERANCE);
        self::assertEqualsWithDelta($probe->baselineOf(2), $baselines[2], self::TOLERANCE);
    }
}
