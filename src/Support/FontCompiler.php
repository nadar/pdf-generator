<?php

namespace Nadar\PdfGenerator\Support;

use Nadar\PdfGenerator\Exception\FontException;
use TCPDF_FONTS;

final class FontCompiler
{
    public static function compile(string $ttf, string $outPath): string
    {
        self::ensureDirectory($outPath);
        $key = TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 32, $outPath);
        if ($key === false || $key === '') {
            throw new FontException(sprintf('Unable to compile TTF "%s".', $ttf));
        }

        return (string) $key;
    }

    /** @return array<string,string> */
    public static function compileDirectory(string $dir, string $outPath): array
    {
        self::ensureDirectory($dir);
        self::ensureDirectory($outPath);

        $result = [];
        foreach (glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.ttf') ?: [] as $ttf) {
            $result[basename($ttf)] = self::compile($ttf, $outPath);
        }

        return $result;
    }

    public static function keyFor(string $ttfBasename): string
    {
        $base = strtolower(pathinfo($ttfBasename, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?? '';
        $base = preg_replace('/bolditalic|italicbold/', 'bi', $base) ?? '';
        $base = preg_replace('/(oblique|italic)/', 'i', $base) ?? '';
        $base = preg_replace('/bold/', 'b', $base) ?? '';
        $base = preg_replace('/regular/', '', $base) ?? '';

        return $base;
    }

    private static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            throw new FontException(sprintf('Directory "%s" does not exist.', $path));
        }
    }
}
