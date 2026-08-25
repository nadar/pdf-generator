<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Align;
use Nadar\PdfGenerator\Exception\InvalidValueException;
use Nadar\PdfGenerator\Exception\PdfGeneratorException;
use Nadar\PdfGenerator\ImageBox;
use Nadar\PdfGenerator\QrBox;
use Nadar\PdfGenerator\Shape;
use Nadar\PdfGenerator\TextBox;
use PHPUnit\Framework\TestCase;

/**
 * Catching {@see PdfGeneratorException} must catch everything this package
 * throws, including argument validation in the value objects.
 */
final class ExceptionHierarchyTest extends TestCase
{
    /** @return iterable<string,array{0:callable():mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'negative corner radius' => [static fn () => Shape::roundRect(-1.0)];
        yield 'zero maxLines' => [static fn () => new TextBox('a', 1, 2, 3, maxLines: 0)];
        yield 'unknown alignment' => [static fn () => Align::coerce('middle')];
        yield 'zero-width image box' => [static fn () => new ImageBox('a', 0, 0, 0, 10)];
        yield 'zero-size qr box' => [static fn () => new QrBox('a', 0, 0, 0)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function testInvalidValuesAreCatchableAsAPackageException(callable $trigger): void
    {
        try {
            $trigger();
        } catch (PdfGeneratorException $exception) {
            self::assertInstanceOf(InvalidValueException::class, $exception);

            return;
        }

        self::fail('no exception was thrown');
    }

    /** And still catchable as the SPL type, so existing handlers keep working. */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function testInvalidValuesRemainSplInvalidArgumentExceptions(callable $trigger): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $trigger();
    }
}
