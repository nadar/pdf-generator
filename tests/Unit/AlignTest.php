<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Align;
use PHPUnit\Framework\TestCase;

final class AlignTest extends TestCase
{
    public function testBackingValuesAreTheCodesTcpdfExpects(): void
    {
        self::assertSame('L', Align::Left->value);
        self::assertSame('C', Align::Center->value);
        self::assertSame('R', Align::Right->value);
        self::assertSame('J', Align::Justify->value);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('coercions')]
    public function testCoerceAcceptsLegacyStringsAndNames(string $input, Align $expected): void
    {
        self::assertSame($expected, Align::coerce($input));
    }

    /** @return iterable<string,array{0:string,1:Align}> */
    public static function coercions(): iterable
    {
        yield 'legacy L' => ['L', Align::Left];
        yield 'lowercase code' => ['c', Align::Center];
        yield 'name' => ['right', Align::Right];
        yield 'british spelling' => ['centre', Align::Center];
        yield 'padded' => ['  justify  ', Align::Justify];
    }

    public function testCoercePassesEnumsThrough(): void
    {
        self::assertSame(Align::Center, Align::coerce(Align::Center));
    }

    public function testCoerceRejectsUnknownValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown alignment "middle"/');
        Align::coerce('middle');
    }
}
