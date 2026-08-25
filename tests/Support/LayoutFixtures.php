<?php

namespace Nadar\PdfGenerator\Tests\Support;

use Nadar\PdfGenerator\Layout;
use Nadar\PdfGenerator\TextBox;

/**
 * Layout constants declared with the published array shape.
 *
 * This is how a consumer keeps layouts in constants or config and still has
 * them statically checked - and it keeps the exported alias honest, since
 * static analysis of this file fails if the shape stops resolving.
 *
 * @phpstan-import-type TextBoxArray from TextBox
 */
final class LayoutFixtures
{
    /** @return list<TextBoxArray> */
    public static function posterRow(): array
    {
        return [
            [
                'id' => 'title',
                'x' => 53.2, 'y' => 58.48, 'w' => 120.0, 'h' => 11.5,
                'font' => 'bold', 'size' => 24,
                'align' => 'left', 'overflow' => 'shrinkThenClip',
                'maxLines' => 1, 'minSize' => 13.0,
                'anchor' => 'baseline',
            ],
            [
                'id' => 'meta',
                'x' => 53.2, 'y' => 70.11, 'w' => 120.0, 'h' => 9.5,
                'font' => 'bold', 'size' => 19,
                'maxLines' => 1, 'minSize' => 11.0,
            ],
        ];
    }

    public static function posterRowLayout(): Layout
    {
        return Layout::fromArray(self::posterRow());
    }
}
