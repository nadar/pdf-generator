<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\TextBox;
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
}
