<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Anchor;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\Tests\Support\LayoutFixtures;
use PHPUnit\Framework\TestCase;

/** The published `TextBoxArray` alias must stay usable by consumers. */
final class LayoutShapeTest extends TestCase
{
    public function testShapedRowsBuildTheExpectedBoxes(): void
    {
        $layout = LayoutFixtures::posterRowLayout();

        self::assertSame(['title', 'meta'], $layout->ids());

        $title = $layout->text('title');
        self::assertSame(Align::Left, $title->align);
        self::assertSame(Anchor::Baseline, $title->anchor);
        self::assertSame(Overflow::ShrinkThenClip, $title->overflow);
        self::assertSame(1, $title->maxLines);
        self::assertSame(13.0, $title->minSize);
        self::assertSame(24.0, $title->size);

        $meta = $layout->text('meta');
        self::assertSame(Anchor::Top, $meta->anchor, 'omitted keys fall back to defaults');
        self::assertNull($meta->overflow);
    }
}
