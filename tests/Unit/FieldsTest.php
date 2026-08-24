<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Support\Fields;
use PHPUnit\Framework\TestCase;

final class FieldsTest extends TestCase
{
    public function testGetFallsBackBetweenDashAndUnderscore(): void
    {
        $data = ['invoice_number' => '  INV-1  '];

        self::assertSame('INV-1', Fields::get($data, 'invoice-number'));
    }
}
