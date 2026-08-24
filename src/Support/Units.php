<?php

namespace Nadar\PdfGenerator\Support;

final class Units
{
    public static function mmToPt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    public static function ptToMm(float $pt): float
    {
        return $pt * 25.4 / 72;
    }

    public static function inToMm(float $in): float
    {
        return $in * 25.4;
    }

    /** @return list<float> */
    public static function pageSize(string $format, string $orientation = 'P'): array
    {
        $sizes = [
            'A4' => [210.0, 297.0],
            'A3' => [297.0, 420.0],
            'A5' => [148.0, 210.0],
            'LETTER' => [215.9, 279.4],
            'LEGAL' => [215.9, 355.6],
        ];

        $key = strtoupper($format);
        $size = $sizes[$key] ?? $sizes['A4'];

        if (strtoupper($orientation) === 'L') {
            return [$size[1], $size[0]];
        }

        return $size;
    }
}
