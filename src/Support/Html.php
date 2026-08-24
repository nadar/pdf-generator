<?php

namespace Nadar\PdfGenerator\Support;

final class Html
{
    public static function b(string $text): string
    {
        return '<b>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
    }

    public static function lines(string ...$lines): string
    {
        return implode('<br/>', array_map(
            static fn(string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $lines
        ));
    }
}
