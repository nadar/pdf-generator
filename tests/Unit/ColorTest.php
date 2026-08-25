<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Value\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testHexParsing(): void
    {
        self::assertSame([34, 55, 100], Color::hex('#223764')->toArray());
        self::assertSame([34, 55, 100], Color::hex('223764')->toArray());
        self::assertSame([255, 153, 0], Color::hex('#f90')->toArray());
        self::assertSame([255, 146, 12], Color::hex('#ff920c')->toArray());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badHex')]
    public function testInvalidHexIsRejectedRatherThanSilentlyBlack(string $hex): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Invalid hex colour/');
        Color::hex($hex);
    }

    /** @return iterable<string,array{0:string}> */
    public static function badHex(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['#12'];
        yield 'odd length' => ['#12345'];
        yield 'not hex' => ['#gggggg'];
        yield 'word' => ['red'];
    }

    /** toArray() is the documented accessor; it must match the raw channels. */
    public function testToArrayMatchesChannelsForEveryModel(): void
    {
        foreach ([Color::rgb(1, 2, 3), Color::cmyk(10, 20, 30, 40), Color::gray(128)] as $color) {
            self::assertSame($color->channels, $color->toArray());
        }
    }

    public function testChannelCounts(): void
    {
        self::assertCount(3, Color::rgb(1, 2, 3)->toArray());
        self::assertCount(4, Color::cmyk(0, 0, 0, 100)->toArray());
        self::assertCount(1, Color::gray(0)->toArray());
    }

    public function testChannelsAreClamped(): void
    {
        self::assertSame([255, 0, 255], Color::rgb(300, -20, 255)->toArray());
        self::assertSame([100.0, 0.0, 0.0, 50.0], Color::cmyk(180, -5, 0, 50)->toArray());
    }

    public function testRgb255ConvertsEveryModel(): void
    {
        self::assertSame([34, 55, 100], Color::hex('#223764')->rgb255());
        self::assertSame([128, 128, 128], Color::gray(128)->rgb255());
        self::assertSame([0, 0, 0], Color::cmyk(0, 0, 0, 100)->rgb255());
        self::assertSame([255, 255, 255], Color::cmyk(0, 0, 0, 0)->rgb255());
    }

    public function testToHex(): void
    {
        self::assertSame('#223764', Color::hex('#223764')->toHex());
        self::assertSame('#000000', Color::black()->toHex());
        self::assertSame('#FFFFFF', Color::white()->toHex());
    }

    public function testNamedConstructors(): void
    {
        self::assertSame(Color::MODEL_GRAY, Color::black()->model);
        self::assertSame([0], Color::black()->toArray());
        self::assertSame([255], Color::white()->toArray());
    }
}
