<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Anchor;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\TextBox;
use Nadar\PdfGenerator\Value\Color;
use PHPUnit\Framework\TestCase;

final class TextBoxTest extends TestCase
{
    public function testFromArrayAndOffset(): void
    {
        $box = TextBox::fromArray([
            'id' => 'a',
            'x' => 10,
            'y' => 20,
            'w' => 30,
            'overflow' => 'ShrinkThenClip',
        ]);

        self::assertSame(Overflow::ShrinkThenClip, $box->overflow);
        self::assertSame(12.0, $box->offset(2, 0)->x);
    }

    public function testDefaults(): void
    {
        $box = new TextBox('a', 1, 2, 3);

        self::assertSame(Align::Left, $box->align);
        self::assertSame(Anchor::Top, $box->anchor);
        self::assertNull($box->h);
        self::assertNull($box->overflow);
        self::assertNull($box->minSize);
        self::assertNull($box->maxLines);
        self::assertFalse($box->html);
        self::assertSame(0.0, $box->rotation);
    }

    public function testAlignAcceptsEnumAndLegacyString(): void
    {
        self::assertSame(Align::Center, (new TextBox('a', 1, 2, 3, align: Align::Center))->align);
        self::assertSame(Align::Center, (new TextBox('a', 1, 2, 3, align: 'C'))->align);
        self::assertSame(Align::Right, (new TextBox('a', 1, 2, 3, align: 'right'))->align);
    }

    /**
     * A flat 6pt floor is invisible in print, so the default floor is relative
     * to the requested size and an explicit value still wins.
     */
    public function testMinSizeDefaultsToAFractionOfTheRequestedSize(): void
    {
        self::assertSame(0.6, TextBox::DEFAULT_MIN_SIZE_RATIO);

        $relative = new TextBox('a', 1, 2, 3);
        self::assertEqualsWithDelta(14.4, $relative->minSizeFor(24.0), 0.0001);
        self::assertEqualsWithDelta(6.0, $relative->minSizeFor(10.0), 0.0001);

        $explicit = new TextBox('a', 1, 2, 3, minSize: 8.0);
        self::assertSame(8.0, $explicit->minSizeFor(24.0));
        self::assertSame(8.0, $explicit->minSizeFor(10.0));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('anchorNames')]
    public function testAnchorFromArray(string $input, Anchor $expected): void
    {
        self::assertSame($expected, TextBox::fromArray(['id' => 'a', 'anchor' => $input])->anchor);
    }

    /** @return iterable<string,array{0:string,1:Anchor}> */
    public static function anchorNames(): iterable
    {
        yield 'top' => ['top', Anchor::Top];
        yield 'cell top alias' => ['cell-top', Anchor::Top];
        yield 'baseline' => ['baseline', Anchor::Baseline];
        yield 'cap height' => ['capHeight', Anchor::CapHeight];
        yield 'snake cap height' => ['cap_height', Anchor::CapHeight];
        yield 'ink top alias' => ['inkTop', Anchor::CapHeight];
    }

    public function testUnknownAnchorIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown anchor "middle"/');
        TextBox::fromArray(['id' => 'a', 'anchor' => 'middle']);
    }

    public function testMaxLinesMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/maxLines for box "a" must be at least 1/');
        new TextBox('a', 1, 2, 3, maxLines: 0);
    }

    public function testWithReplacesOnlyWhatIsGiven(): void
    {
        $box = new TextBox('a', 1, 2, 3, 4.0, 'bold', 12.0, Align::Center, Color::hex('#223764'), Overflow::Clip, 90.0, 8.0, true, Anchor::Baseline, 2);
        $copy = $box->with(size: 10.0);

        self::assertSame(10.0, $copy->size);
        self::assertSame('a', $copy->id);
        self::assertSame('bold', $copy->font);
        self::assertSame(Align::Center, $copy->align);
        self::assertSame(Anchor::Baseline, $copy->anchor);
        self::assertSame(Overflow::Clip, $copy->overflow);
        self::assertSame(2, $copy->maxLines);
        self::assertSame(8.0, $copy->minSize);
        self::assertTrue($copy->html);
        self::assertSame(90.0, $copy->rotation);
    }

    public function testWithCanRetargetAnchorAndAlign(): void
    {
        $box = new TextBox('a', 1, 2, 3, anchor: Anchor::Baseline);

        self::assertSame(Anchor::Top, $box->with(anchor: Anchor::Top)->anchor);
        self::assertSame(Align::Right, $box->with(align: 'R')->align);
    }

    public function testAtMovesToAnAbsolutePosition(): void
    {
        $box = (new TextBox('a', 1, 2, 3))->at(50.0, 60.0);

        self::assertSame(50.0, $box->x);
        self::assertSame(60.0, $box->y);
    }

    public function testBoxesAreImmutable(): void
    {
        $box = new TextBox('a', 10, 20, 30);
        $box->offset(5, 5);
        $box->with(size: 99.0);

        self::assertSame(10.0, $box->x);
        self::assertSame(20.0, $box->y);
        self::assertNull($box->size);
    }
}
