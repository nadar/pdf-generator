<?php

namespace Nadar\PdfGenerator\Support;

/**
 * Builders for the small HTML subset an `html: true` box accepts.
 *
 * Each helper escapes its input, so data cannot inject markup.
 */
final class Html
{
    /**
     * Bold, escaped.
     *
     * The family must have a bold face registered - see
     * {@see \Nadar\PdfGenerator\Exception\MissingFontStyleException}.
     */
    public static function b(string $text): string
    {
        return '<b>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
    }

    /** Join escaped lines with `<br/>`. */
    public static function lines(string ...$lines): string
    {
        return implode('<br/>', array_map(
            static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $lines
        ));
    }
}
