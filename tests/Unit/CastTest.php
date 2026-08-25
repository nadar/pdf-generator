<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Support\Cast;
use PHPUnit\Framework\TestCase;

final class CastTest extends TestCase
{
    public function testToStringCoercesScalarsAndNull(): void
    {
        self::assertSame('INV-1', Cast::toString('INV-1', 'invoice'));
        self::assertSame('42', Cast::toString(42, 'invoice'));
        self::assertSame('', Cast::toString(null, 'invoice'));
    }

    public function testToStringRejectsArrays(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Value for "invoice" must be stringable, array given.');

        Cast::toString(['nested'], 'invoice');
    }

    public function testToFloatAcceptsNumericStrings(): void
    {
        self::assertSame(12.5, Cast::toFloat(' 12.5 ', 'x'));
        self::assertSame(12.0, Cast::toFloat(12, 'x'));
    }

    public function testToFloatRejectsNonNumericStrings(): void
    {
        $this->expectException(ConfigurationException::class);

        Cast::toFloat('wide', 'w');
    }

    public function testToBoolReadsTextualFlags(): void
    {
        self::assertTrue(Cast::toBool('true', 'html'));
        self::assertFalse(Cast::toBool('false', 'html'));
        self::assertFalse(Cast::toBool('', 'html'));
        self::assertTrue(Cast::toBool(1, 'html'));
    }
}
