<?php

namespace Nadar\PdfGenerator\Font;

/**
 * Normalises weight names and maps them onto TCPDF's font model.
 *
 * TCPDF only knows four styles per family (`''`, `B`, `I`, `BI`), but brand
 * kits routinely ship Regular/Medium/Bold/ExtraBold. Weights outside the four
 * canonical ones are therefore registered as their own TCPDF family - `brother`
 * plus weight `medium` becomes the TCPDF family `brothermedium` - which keeps
 * one logical family in the settings while still embedding a real face for
 * every weight.
 */
final class FontWeight
{
    public const REGULAR = 'regular';
    public const BOLD = 'bold';
    public const ITALIC = 'italic';
    public const BOLD_ITALIC = 'bolditalic';

    /** Weights TCPDF can express as a style on the same family. */
    public const CANONICAL = [
        self::REGULAR => '',
        self::BOLD => 'B',
        self::ITALIC => 'I',
        self::BOLD_ITALIC => 'BI',
    ];

    /**
     * Canonical weight name for a weight label or a TCPDF style code.
     *
     * Accepts `''`/`B`/`I`/`BI` as well as spelled-out names, so existing
     * `role('bold', 'inter', 'B')` calls keep working along
     * `role('lead', 'inter', 'medium')`.
     */
    public static function normalize(string $weight): string
    {
        $value = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $weight));

        return match ($value) {
            '', 'r', 'regular', 'normal', 'book' => self::REGULAR,
            'b', 'bold' => self::BOLD,
            'i', 'italic', 'oblique' => self::ITALIC,
            'bi', 'ib', 'bolditalic', 'italicbold', 'boldoblique' => self::BOLD_ITALIC,
            default => $value,
        };
    }

    /**
     * The TCPDF family/style pair a logical family plus weight resolves to.
     *
     * @return array{0:string,1:string} `[family, style]`
     */
    public static function toTcpdf(string $family, string $weight): array
    {
        $weight = self::normalize($weight);

        if (isset(self::CANONICAL[$weight])) {
            return [$family, self::CANONICAL[$weight]];
        }

        return [$family . $weight, ''];
    }

    /** Human-readable label used in file-name suggestions, e.g. `BoldItalic`. */
    public static function label(string $weight): string
    {
        $weight = self::normalize($weight);

        return match ($weight) {
            self::REGULAR => 'Regular',
            self::BOLD => 'Bold',
            self::ITALIC => 'Italic',
            self::BOLD_ITALIC => 'BoldItalic',
            default => ucfirst($weight),
        };
    }
}
