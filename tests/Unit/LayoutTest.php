<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Anchor;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\TextBox;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    public function testFromArrayKeysSlotsById(): void
    {
        $layout = self::rowLayout();

        self::assertSame(['title', 'meta'], $layout->ids());
        self::assertCount(2, $layout);
        self::assertTrue($layout->has('title'));
        self::assertFalse($layout->has('nope'));
    }

    public function testFromArrayReadsEveryDocumentedKey(): void
    {
        $box = Layout::fromArray([[
            'id' => 'x', 'x' => 1, 'y' => 2, 'w' => 3, 'h' => 4,
            'font' => 'bold', 'size' => 12, 'align' => 'center', 'color' => '#223764',
            'overflow' => 'shrink-then-clip', 'rotation' => 90, 'minSize' => 8,
            'html' => true, 'anchor' => 'baseline', 'maxLines' => 2,
        ]])->get('x');

        self::assertSame(Align::Center, $box->align);
        self::assertSame(Anchor::Baseline, $box->anchor);
        self::assertSame(Overflow::ShrinkThenClip, $box->overflow);
        self::assertSame(2, $box->maxLines);
        self::assertSame(8.0, $box->minSize);
        self::assertTrue($box->html);
        self::assertSame(90.0, $box->rotation);
        self::assertSame([34, 55, 100], $box->color?->toArray());
    }

    public function testGetNamesKnownSlotsWhenMissing(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/no slot "nope".*Known slots: title, meta/');
        self::rowLayout()->get('nope');
    }

    public function testOffsetShiftsEverySlot(): void
    {
        $shifted = self::rowLayout()->offset(5.0, 10.0);

        self::assertSame(25.0, $shifted->get('title')->x);
        self::assertSame(30.0, $shifted->get('title')->y);
        self::assertSame(80.0, $shifted->get('meta')->y);
    }

    /** The declarative form of the "n identical slots" pattern. */
    public function testRepeatProducesOneOffsetLayoutPerRow(): void
    {
        $rows = self::rowLayout()->repeat(times: 6, dy: 40.3);

        self::assertCount(6, $rows);
        // the first copy is the layout itself, unshifted
        self::assertSame(20.0, $rows[0]->get('title')->y);
        self::assertSame(60.3, $rows[1]->get('title')->y);
        self::assertEqualsWithDelta(221.5, $rows[5]->get('title')->y, 0.0001);
        // horizontal pitch stays untouched
        self::assertSame(20.0, $rows[5]->get('title')->x);
    }

    public function testRepeatSupportsColumnGrids(): void
    {
        $columns = self::rowLayout()->repeat(times: 3, dx: 60.0);

        self::assertSame(20.0, $columns[0]->get('title')->x);
        self::assertSame(140.0, $columns[2]->get('title')->x);
        self::assertSame(20.0, $columns[2]->get('title')->y);
    }

    public function testRepeatRejectsZeroTimes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::rowLayout()->repeat(times: 0, dy: 10.0);
    }

    public function testMakeAndWith(): void
    {
        $layout = Layout::make(new TextBox('a', 1, 2, 3));
        $extended = $layout->with(new TextBox('b', 4, 5, 6), new TextBox('a', 9, 9, 9));

        self::assertSame(['a'], $layout->ids());
        self::assertSame(['a', 'b'], $extended->ids());
        self::assertSame(9.0, $extended->get('a')->x, 'same id replaces the slot');
        self::assertSame(1.0, $layout->get('a')->x, 'the original is untouched');
    }

    public function testIsIterableAsSlotIdToBox(): void
    {
        $seen = [];
        foreach (self::rowLayout() as $id => $box) {
            $seen[$id] = $box->id;
        }

        self::assertSame(['title' => 'title', 'meta' => 'meta'], $seen);
    }

    private static function rowLayout(): Layout
    {
        return Layout::fromArray([
            ['id' => 'title', 'x' => 20, 'y' => 20, 'w' => 120, 'h' => 11.5],
            ['id' => 'meta', 'x' => 20, 'y' => 70, 'w' => 120, 'h' => 9.5],
        ]);
    }
}
