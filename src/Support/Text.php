<?php

namespace Nadar\PdfGenerator\Support;

/** String normalisation applied to every value before it is written. */
final class Text
{
    /**
     * Normalise line endings to `\n` and **trim** surrounding whitespace.
     *
     * Every {@see \Nadar\PdfGenerator\PdfGenerator::write()} runs its input
     * through this, so leading or trailing whitespace never shifts text inside
     * a slot. Use a non-breaking space if you genuinely need leading space.
     */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    /** Strip tags and decode entities - HTML reduced to the text a human reads. */
    public static function plain(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Escape plain text for an HTML box, turning newlines into `<br/>`.
     *
     * Use this for any user-supplied value going into an `html: true` box;
     * writing it raw would let markup in the data change the layout.
     */
    public static function forHtmlCell(string $text): string
    {
        return nl2br(htmlspecialchars(self::normalize($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Truncate to at most $max characters at a word boundary, adding an ellipsis.
     *
     * Character-based, so it is cheap but layout-blind. For a real column width
     * use {@see \Nadar\PdfGenerator\PdfGenerator::truncateToWidth()}, which
     * measures the actual glyphs.
     */
    public static function truncateChars(string $text, int $max, string $ellipsis = '...'): string
    {
        $text = self::normalize($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $maxBody = max(0, $max - mb_strlen($ellipsis));
        $slice = rtrim(mb_substr($text, 0, $maxBody));
        $nextChar = mb_substr($text, $maxBody, 1);
        if ($nextChar !== '' && !preg_match('/\s/u', $nextChar)) {
            $slice = rtrim((string) preg_replace('/\s+\S*$/u', '', $slice));
        }

        if ($slice === '') {
            $slice = mb_substr($text, 0, $maxBody);
        }

        return $slice . $ellipsis;
    }
}
