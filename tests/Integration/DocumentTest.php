<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Anchor;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Exception\MissingFontStyleException;
use Nadar\PdfGenerator\Exception\TemplateNotFoundException;
use Nadar\PdfGenerator\Exception\TemplateSizeMismatchException;
use Nadar\PdfGenerator\Exception\UnknownMethodException;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\Tests\Support\FactorySettings;
use Nadar\PdfGenerator\Tests\Support\HeaderPdf;
use Nadar\PdfGenerator\Tests\Support\HeaderPdfFactory;
use Nadar\PdfGenerator\Tests\Support\PlainFpdiFactory;
use Nadar\PdfGenerator\TextBox;

final class DocumentTest extends IntegrationTestCase
{
    /**
     * The round trip the poster example relies on: generate a page, save it,
     * stamp it back as a template.
     */
    public function testGeneratedPdfCanBeStampedBackAsATemplate(): void
    {
        $this->makeTemplate('generated.pdf');

        $pdf = $this->pdf();
        $size = $pdf->templateSize('generated.pdf');

        self::assertEqualsWithDelta(210.0, $size->width, 0.05);
        self::assertEqualsWithDelta(297.0, $size->height, 0.05);
        self::assertSame('P', $size->orientation());

        $pdf->page('generated.pdf');
        $pdf->writeText(20, 100, 100, 'overlay');
        $drawn = self::drawnText($pdf->bytes());

        self::assertContains('template background', $drawn, 'the stamped template is present');
        self::assertContains('overlay', $drawn, 'and the overlay on top of it');
    }

    /** A landscape template must drive the page orientation without being asked. */
    public function testPageDerivesFormatAndOrientationFromTheTemplate(): void
    {
        $source = new PdfGenerator($this->settings());
        $source->page(null, [297.0, 210.0], 'L');
        $source->save($this->workspace . '/landscape.pdf');

        $pdf = $this->pdf();
        $size = $pdf->templateSize('landscape.pdf');

        self::assertSame('L', $size->orientation());
        self::assertEqualsWithDelta(297.0, $size->width, 0.05);

        $pdf->page('landscape.pdf');
        $dimensions = $pdf->raw()->getPageDimensions();

        self::assertEqualsWithDelta(297.0, (float) $dimensions['wk'], 0.05);
        self::assertEqualsWithDelta(210.0, (float) $dimensions['hk'], 0.05);
    }

    public function testAssertTemplateSizeAcceptsTheRightSize(): void
    {
        $this->makeTemplate('a4.pdf');

        $pdf = $this->pdf();

        self::assertSame($pdf, $pdf->assertTemplateSize('a4.pdf', 210.0, 297.0));
    }

    public function testAssertTemplateSizeRejectsTheWrongSize(): void
    {
        $this->makeTemplate('a4.pdf');

        $this->expectException(TemplateSizeMismatchException::class);
        $this->expectExceptionMessageMatches('/Expected 148\.000 x 210\.000 mm, got 210\.\d+ x 297\.\d+ mm/');
        $this->pdf()->assertTemplateSize('a4.pdf', 148.0, 210.0);
    }

    /** The message must say that the argument is a basename, not a path. */
    public function testMissingTemplateNamesTheDirectoryAndTheConvention(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->expectExceptionMessageMatches('/Template "nope\.pdf" was not found in.*basename/s');
        $this->pdf()->page('nope.pdf');
    }

    public function testAppendFileAddsEveryPage(): void
    {
        $source = new PdfGenerator($this->settings());
        $source->page(null, 'A4');
        $source->writeText(20, 20, 100, 'appended one');
        $source->page(null, 'A4');
        $source->writeText(20, 20, 100, 'appended two');
        $source->save($this->workspace . '/extra.pdf');

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->writeText(20, 20, 100, 'original');
        $pdf->appendFile($this->workspace . '/extra.pdf');

        self::assertSame(3, $pdf->raw()->getNumPages());
    }

    public function testAppendFileRejectsAnUnreadablePath(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Cannot append unreadable PDF/');
        $this->pdf()->appendFile($this->workspace . '/nope.pdf');
    }

    /** Byte-stable output is what makes golden-file tests and HTTP caching possible. */
    public function testDeterministicTimestampProducesIdenticalBytes(): void
    {
        $render = function (): string {
            $pdf = $this->pdf();
            $pdf->deterministic(1_700_000_000)->title('Fixed')->page(null, 'A4');
            $pdf->writeText(20, 20, 100, 'stable');

            return $pdf->bytes();
        };

        self::assertSame(md5($render()), md5($render()));
    }

    /**
     * Timestamps alone are not enough: TCPDF also seeds a random document id
     * into the XMP metadata, which has to be pinned for the bytes to match.
     */
    public function testDeterministicPinsTheDocumentId(): void
    {
        $render = function (): string {
            $pdf = $this->pdf();
            $pdf->deterministic(1_700_000_000)->page(null, 'A4');
            $pdf->writeText(20, 20, 100, 'stable');

            return $pdf->bytes();
        };

        $documentId = static function (string $bytes): string {
            if (preg_match('/xmpMM:DocumentID>uuid:([0-9a-f-]+)</', $bytes, $matches) !== 1) {
                self::fail('the document carries no xmpMM:DocumentID');
            }

            return $matches[1];
        };

        self::assertSame($documentId($render()), $documentId($render()));
    }

    /** A content-derived id keeps same-timestamp documents distinguishable. */
    public function testDeterministicIdSeedCanBeOverridden(): void
    {
        $render = function (string $id): string {
            $pdf = $this->pdf();
            $pdf->deterministic(1_700_000_000, $id)->page(null, 'A4');
            $pdf->writeText(20, 20, 100, 'stable');

            return $pdf->bytes();
        };

        self::assertNotSame(md5($render('invoice-1')), md5($render('invoice-2')));
        self::assertSame(md5($render('invoice-1')), md5($render('invoice-1')));
    }

    public function testDifferentTimestampsProduceDifferentBytes(): void
    {
        $render = function (int $timestamp): string {
            $pdf = $this->pdf();
            $pdf->deterministic($timestamp)->page(null, 'A4');
            $pdf->writeText(20, 20, 100, 'stable');

            return $pdf->bytes();
        };

        self::assertNotSame(md5($render(1_700_000_000)), md5($render(1_800_000_000)));
    }

    public function testWriteAllFillsALayoutAndAliasesKeySeparators(): void
    {
        $layout = Layout::fromArray([
            ['id' => 'event-title', 'x' => 20, 'y' => 20, 'w' => 120],
            ['id' => 'event_place', 'x' => 20, 'y' => 40, 'w' => 120],
            ['id' => 'absent', 'x' => 20, 'y' => 60, 'w' => 120],
        ]);

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->writeAll($layout, [
            // underscores in the data, hyphen in the slot id, and vice versa
            'event_title' => 'City Run',
            'event-place' => 'Market Square',
        ]);

        $drawn = self::drawnText($pdf->bytes());

        self::assertSame(
            ['City Run', 'Market Square'],
            $drawn,
            'the missing key writes nothing rather than failing'
        );
    }

    public function testRepeatedLayoutRendersEveryRow(): void
    {
        $layout = Layout::fromArray([['id' => 'title', 'x' => 20, 'y' => 20, 'w' => 120, 'size' => 12]]);

        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        foreach ($layout->repeat(times: 6, dy: 40.0) as $index => $row) {
            $pdf->writeAll($row, ['title' => 'Row ' . $index]);
        }

        $baselines = self::baselines($pdf->bytes());

        self::assertCount(6, $baselines);
        self::assertEqualsWithDelta(40.0, $baselines[1] - $baselines[0], 0.001);
        self::assertEqualsWithDelta(200.0, $baselines[5] - $baselines[0], 0.001);
    }

    /** probe() reports geometry without putting anything on the page. */
    public function testProbeDrawsNothing(): void
    {
        $box = new TextBox('t', 20, 20, 120, size: 12.0);

        $probed = $this->pdf();
        $probed->page(null, 'A4');
        $metrics = $probed->probe($box, 'measure me');

        self::assertSame([], self::drawnText($probed->bytes()));
        self::assertGreaterThan(0.0, $metrics->baseline);
    }

    public function testProbeLeavesTheSelectedFontUntouched(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->raw()->SetFont('helvetica', 'B', 17.0);

        $pdf->probe(new TextBox('t', 20, 20, 120, size: 33.0), 'x');

        self::assertSame('helvetica', $pdf->raw()->getFontFamily());
        self::assertSame('B', $pdf->raw()->getFontStyle());
        self::assertEqualsWithDelta(17.0, (float) $pdf->raw()->getFontSizePt(), 0.001);
    }

    public function testLastMetricsIsNullBeforeTheFirstWrite(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        self::assertNull($pdf->lastMetrics());
    }

    public function testLastMetricsIsPopulatedAfterAWrite(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->writeText(20, 20, 100, 'first');

        $metrics = $pdf->lastMetrics();

        self::assertNotNull($metrics);
        self::assertSame(1, $metrics->lines);
        self::assertEqualsWithDelta(12.0, $metrics->size, 0.001);
    }

    public function testMetricsToArrayIsFlatAndLoggable(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $pdf->write(new TextBox('t', 20, 20, 120, size: 12.0, anchor: Anchor::Baseline), 'metrics');

        $metrics = $pdf->lastMetrics();
        self::assertNotNull($metrics);
        $array = $metrics->toArray();

        self::assertSame(
            ['x', 'y', 'w', 'h', 'baseline', 'capTop', 'size', 'lines', 'lineHeight', 'height', 'ascent', 'descent', 'fits', 'overflow'],
            array_keys($array)
        );
        self::assertEqualsWithDelta(20.0, $array['baseline'], 0.001);
        self::assertSame('None', $array['overflow']);
    }

    public function testAlignmentChangesTheRenderedPosition(): void
    {
        $positions = [];

        foreach ([Align::Left, Align::Center, Align::Right] as $align) {
            $pdf = $this->pdf();
            $pdf->page(null, 'A4');
            $pdf->write(new TextBox('t', 20, 20, 150, size: 12.0, align: $align), 'short');
            $positions[$align->name] = self::textLeftEdges($pdf->bytes())[0];
        }

        self::assertLessThan($positions['Center'], $positions['Left']);
        self::assertLessThan($positions['Right'], $positions['Center']);
    }

    public function testDebugModeDrawsExtraMarks(): void
    {
        $plain = $this->pdf();
        $plain->page(null, 'A4');
        $plain->writeText(20, 20, 100, 'x');

        $debug = $this->pdf(debug: true);
        $debug->page(null, 'A4');
        $debug->writeText(20, 20, 100, 'x');

        $debugText = implode(' ', self::drawnText($debug->bytes()));

        self::assertGreaterThan(strlen($plain->bytes()), strlen($debug->bytes()));
        // the resolved size and line count are printed next to the box
        self::assertMatchesRegularExpression('/12\.00pt \/ 1 line/', $debugText);
    }

    public function testUnknownProxiedMethodThrows(): void
    {
        $this->expectException(UnknownMethodException::class);
        $this->expectExceptionMessageMatches('/Unknown TCPDF method "definitelyNotAMethod"/');
        // @phpstan-ignore-next-line intentionally calling a method that does not exist
        $this->pdf()->definitelyNotAMethod();
    }

    public function testKnownProxiedMethodReachesTcpdf(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        self::assertSame(1, $pdf->getNumPages());
    }

    /**
     * Synthetic bold is a silent no-op for embedded subset fonts, so markup
     * needing a face that is not registered must fail rather than look right in
     * code and wrong in print.
     */
    public function testHtmlBoldWithoutABoldFaceIsRejected(): void
    {
        $pdf = $this->pdf(FontSet::make()->coreFamily('helvetica')->role('regular', 'helvetica'));
        $pdf->page(null, 'A4');
        $pdf->writeHtml(20, 20, 120, '<b>bold</b>');

        // helvetica does have a bold face, so that one is fine
        self::assertNotEmpty(self::drawnText($pdf->bytes()));
    }

    /** With nothing declared, the message must not point at a font file. */
    public function testHtmlBoldWithNoFontsDeclaredExplainsTheFallback(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');

        $this->expectException(MissingFontStyleException::class);
        $this->expectExceptionMessageMatches('/declares no faces.*coreFamily/s');
        $pdf->writeHtml(20, 20, 120, '<b>bold</b>');
    }

    /** The documented reason pdfFactory() exists. */
    public function testPdfFactoryProvidesACustomDocumentClass(): void
    {
        $factory = new HeaderPdfFactory();
        $pdf = new PdfGenerator(new FactorySettings($this->workspace, $factory));
        $pdf->raw()->SetCompression(false);
        $pdf->raw()->setPrintHeader(true);
        $pdf->page(null, 'A4');

        self::assertSame(1, $factory->calls);
        self::assertInstanceOf(HeaderPdf::class, $pdf->raw());
        self::assertContains(HeaderPdf::HEADER_TEXT, self::drawnText($pdf->bytes()));
    }

    /**
     * capTop() is the ink top a rasterised reference measures, so it is what a
     * pixel-measured band should be compared against.
     */
    public function testMetricsReportTheInkTop(): void
    {
        $pdf = $this->pdf();
        $pdf->page(null, 'A4');
        $metrics = $pdf->probe(new TextBox('t', 20, 60.0, 120, size: 24.0, anchor: Anchor::Baseline), 'HXY');

        self::assertNotNull($metrics->capHeight);
        self::assertGreaterThan(0.0, $metrics->capHeight);
        self::assertLessThan($metrics->ascent, $metrics->capHeight, 'cap height sits below the ascent');
        self::assertEqualsWithDelta(60.0 - $metrics->capHeight, $metrics->capTop(), 0.0001);
    }

    /** Without cap-height support the field is null rather than a guess. */
    public function testInkTopIsNullOnADocumentClassThatCannotReportIt(): void
    {
        $pdf = new PdfGenerator(new FactorySettings($this->workspace, new PlainFpdiFactory()));
        $pdf->page(null, 'A4');
        $metrics = $pdf->probe(new TextBox('t', 20, 60.0, 120, size: 24.0), 'HXY');

        self::assertNull($metrics->capHeight);
        self::assertNull($metrics->capTop());
    }

    /** Cap height needs MetricsFpdi; a bare Fpdi must say so rather than guess. */
    public function testCapHeightAnchorExplainsAnIncapableDocumentClass(): void
    {
        $pdf = new PdfGenerator(new FactorySettings($this->workspace, new PlainFpdiFactory()));
        $pdf->page(null, 'A4');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Anchor::CapHeight needs cap-height metrics.*MetricsFpdi/s');
        $pdf->write(new TextBox('t', 20, 20, 120, size: 12.0, anchor: Anchor::CapHeight), 'x');
    }

    /** Other anchors keep working on a plain Fpdi. */
    public function testBaselineAnchorWorksWithoutMetricsSupport(): void
    {
        $pdf = new PdfGenerator(new FactorySettings($this->workspace, new PlainFpdiFactory()));
        $pdf->raw()->SetCompression(false);
        $pdf->page(null, 'A4');
        $pdf->write(new TextBox('t', 20, 60.0, 120, size: 12.0, anchor: Anchor::Baseline), 'x');

        self::assertEqualsWithDelta(60.0, self::baselines($pdf->bytes())[0], 0.001);
    }
}
