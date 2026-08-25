<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Overflow;
use PHPUnit\Framework\TestCase;

final class OverflowTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('names')]
    public function testFromStringAcceptsEveryCasing(string $input, Overflow $expected): void
    {
        self::assertSame($expected, Overflow::fromString($input));
    }

    /** @return iterable<string,array{0:string,1:Overflow}> */
    public static function names(): iterable
    {
        yield 'none' => ['none', Overflow::None];
        yield 'shrink' => ['Shrink', Overflow::Shrink];
        yield 'clip' => ['CLIP', Overflow::Clip];
        yield 'truncate' => ['truncate', Overflow::Truncate];
        yield 'camel' => ['shrinkThenClip', Overflow::ShrinkThenClip];
        yield 'snake' => ['shrink_then_clip', Overflow::ShrinkThenClip];
        yield 'kebab' => ['shrink-then-clip', Overflow::ShrinkThenClip];
        yield 'padded' => ['  shrink  ', Overflow::Shrink];
    }

    public function testFromStringRejectsUnknownNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown overflow value "wrap".*shrinkThenClip/s');
        Overflow::fromString('wrap');
    }

    public function testShrinksIdentifiesTheResizingPolicies(): void
    {
        self::assertTrue(Overflow::Shrink->shrinks());
        self::assertTrue(Overflow::ShrinkThenClip->shrinks());
        self::assertFalse(Overflow::None->shrinks());
        self::assertFalse(Overflow::Clip->shrinks());
        self::assertFalse(Overflow::Truncate->shrinks());
    }
}
