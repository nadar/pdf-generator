<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Support\FontCompiler;
use PHPUnit\Framework\TestCase;

final class FontCompilerTest extends TestCase
{
    public function testKeyDerivationForBoldFace(): void
    {
        self::assertSame('brandb', FontCompiler::keyFor('Brand-Bold.ttf'));
        self::assertSame('brand', FontCompiler::keyFor('Brand-Regular.ttf'));
        self::assertSame('brandbi', FontCompiler::keyFor('Brand-BoldItalic.ttf'));
    }
}
