<?php

namespace Nadar\PdfGenerator\Support;

final class Text
{
    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    public static function plain(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public static function forHtmlCell(string $text): string
    {
        return nl2br(htmlspecialchars(self::normalize($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

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
