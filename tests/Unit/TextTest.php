<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Support\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testTruncateCharsPreservesWordBoundary(): void
    {
        self::assertSame('The quick...', Text::truncateChars('The quick brown fox', 12));
    }
}
