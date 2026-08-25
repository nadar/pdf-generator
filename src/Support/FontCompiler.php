<?php

namespace Nadar\PdfGenerator\Support;

use Nadar\PdfGenerator\Exception\FontException;
use TCPDF_FONTS;

/**
 * Compiles font files into the TCPDF font definitions the renderer needs.
 *
 * TCPDF cannot embed a `.ttf` directly: every face has to be converted into a
 * `<key>.php` definition (plus `.z`/`.ctg.z` companions) once, ahead of
 * rendering. That is what this class does, and what
 * `vendor/bin/pdf-generator fonts:build` calls.
 *
 * The cache directory must be committed to the repository or rebuilt in CI -
 * the first render fails if a definition is missing.
 */
final class FontCompiler
{
    /** Extensions TCPDF can convert. */
    public const SUPPORTED_EXTENSIONS = ['ttf', 'otf'];

    /**
     * Web font formats TCPDF cannot read, with the reason shown to the caller.
     *
     * Brand kits routinely ship only these, which is why they get a dedicated
     * message instead of a silent skip.
     *
     * @var array<string,string>
     */
    public const UNSUPPORTED_EXTENSIONS = [
        'woff' => 'woff is a compressed web font wrapper',
        'woff2' => 'woff2 is a compressed web font wrapper',
        'eot' => 'eot is a legacy Internet Explorer format',
        'svg' => 'svg fonts carry no usable outline tables for TCPDF',
    ];

    /** Shown whenever an unsupported source format is encountered. */
    public const CONVERSION_HINT = 'Convert it to ttf first, e.g. with fontTools: '
        . 'python3 -c "from fontTools.ttLib import TTFont; f=TTFont(\'in.woff\'); f.flavor=None; f.save(\'out.ttf\')"';

    /**
     * Normalise a directory path to always end in exactly one separator.
     *
     * TCPDF builds its output filename as `$outpath . $key . '.php'` with no
     * separator of its own, so a path without a trailing separator writes
     * `/fonts/cachebrand.php` instead of `/fonts/cache/brand.php` - the build
     * reports success and the renderer then reports a missing cache.
     */
    public static function normalizePath(string $path): string
    {
        return rtrim($path, '/' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** Absolute path of the definition file TCPDF writes for $key. */
    public static function cacheFile(string $outPath, string $key): string
    {
        return self::normalizePath($outPath) . $key . '.php';
    }

    /**
     * Compile a single font file and return its TCPDF font key.
     *
     * @param string $font    path to a `.ttf` (or TrueType-flavoured `.otf`) file
     * @param string $outPath directory the definition is written to; created when missing
     * @param bool   $force   recompile even when a definition already exists.
     *                        TCPDF returns early for an existing definition, so a
     *                        stale cache is never refreshed without this.
     *
     * @throws FontException when the source is unreadable, is a format TCPDF cannot
     *                       read, or conversion fails
     */
    public static function compile(string $font, string $outPath, bool $force = false): string
    {
        if (!is_file($font) || !is_readable($font)) {
            throw new FontException(sprintf('Font file "%s" does not exist or is not readable.', $font));
        }

        self::assertSupportedFormat($font);

        $outPath = self::normalizePath($outPath);
        self::ensureWritableDirectory($outPath);

        $expectedKey = self::keyFor(basename($font));
        $expectedFile = $outPath . $expectedKey . '.php';

        if ($force && is_file($expectedFile)) {
            @unlink($expectedFile);
        }

        $key = TCPDF_FONTS::addTTFfont($font, self::detectFontType($font), '', 32, $outPath);

        if ($key === false || $key === '') {
            throw new FontException(sprintf(
                'TCPDF could not convert "%s". The file carries TrueType tables but '
                . 'could not be parsed; re-exporting it through fontTools usually fixes it. %s',
                $font,
                self::CONVERSION_HINT
            ));
        }

        $key = (string) $key;
        $written = $outPath . $key . '.php';

        if (!is_file($written)) {
            throw new FontException(sprintf(
                'TCPDF reported key "%s" for "%s" but no definition was written to "%s".',
                $key,
                $font,
                $written
            ));
        }

        return $key;
    }

    /**
     * Compile every supported font file in a directory.
     *
     * @param string        $dir      directory holding the font sources
     * @param string        $outPath  directory the definitions are written to
     * @param bool          $force    recompile faces that already have a definition
     * @param null|callable(string):void $onNotice receives one human-readable line per
     *                      skipped file, so a CLI can surface what was ignored instead
     *                      of exiting 0 on a directory it could not use
     *
     * @return array<string,string> source basename => TCPDF font key
     *
     * @throws FontException when the directory holds no usable font, or two files
     *                       would compile to the same key
     */
    public static function compileDirectory(string $dir, string $outPath, bool $force = false, ?callable $onNotice = null): array
    {
        if (!is_dir($dir)) {
            throw new FontException(sprintf('Font directory "%s" does not exist.', $dir));
        }

        $sources = [];
        $unsupported = [];

        foreach (self::scan($dir) as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                $sources[] = $file;
                continue;
            }

            if (isset(self::UNSUPPORTED_EXTENSIONS[$extension])) {
                $unsupported[] = $file;
            }
        }

        if ($sources === []) {
            throw new FontException(self::noUsableFontMessage($dir, $unsupported));
        }

        foreach ($unsupported as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            self::notify($onNotice, sprintf(
                'skipped %s -- %s, unreadable by TCPDF. %s',
                basename($file),
                self::UNSUPPORTED_EXTENSIONS[$extension],
                self::CONVERSION_HINT
            ));
        }

        self::assertNoKeyCollisions($sources);

        $result = [];
        foreach ($sources as $file) {
            $result[basename($file)] = self::compile($file, $outPath, $force);
        }

        return $result;
    }

    /**
     * The TCPDF font key a source file compiles to.
     *
     * Mirrors TCPDF's own derivation exactly (lowercase the filename, drop
     * everything outside `[a-z0-9_]`, then replace `bold`/`oblique`/`italic`/
     * `regular` with `b`/`i`/`i`/`''` in a single pass) so a cache lookup can
     * never disagree with what the build wrote.
     */
    public static function keyFor(string $fontBasename): string
    {
        $name = strtolower(pathinfo($fontBasename, PATHINFO_FILENAME));
        $name = preg_replace('/[^a-z0-9_]/', '', $name) ?? '';
        $name = str_replace(['bold', 'oblique', 'italic', 'regular'], ['b', 'i', 'i', ''], $name);

        // TCPDF falls back to this when nothing is left, e.g. for "Regular.ttf".
        return $name === '' ? 'tcpdffont' : $name;
    }

    /** @return list<string> absolute paths, sorted for reproducible builds */
    private static function scan(string $dir): array
    {
        $entries = scandir($dir);
        if ($entries === false) {
            throw new FontException(sprintf('Font directory "%s" could not be read.', $dir));
        }

        $files = [];
        foreach ($entries as $entry) {
            $path = self::normalizePath($dir) . $entry;
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /** @param list<string> $sources */
    private static function assertNoKeyCollisions(array $sources): void
    {
        /** @var array<string,string> $seen */
        $seen = [];

        foreach ($sources as $file) {
            $key = self::keyFor(basename($file));

            if (isset($seen[$key])) {
                throw new FontException(sprintf(
                    'Font files "%s" and "%s" both compile to key "%s"; the second would '
                    . 'silently overwrite the first. Rename one of them - "regular" and '
                    . 'non-alphanumeric characters are stripped when the key is derived.',
                    basename($seen[$key]),
                    basename($file),
                    $key
                ));
            }

            $seen[$key] = $file;
        }
    }

    /** @param list<string> $unsupported */
    private static function noUsableFontMessage(string $dir, array $unsupported): string
    {
        if ($unsupported === []) {
            return sprintf(
                'No font files found in "%s". Expected at least one of: *.%s.',
                $dir,
                implode(', *.', self::SUPPORTED_EXTENSIONS)
            );
        }

        $names = array_map(static fn (string $file): string => basename($file), $unsupported);

        return sprintf(
            'No usable font files in "%s". Found %d file(s) TCPDF cannot read (%s). %s',
            $dir,
            count($names),
            implode(', ', array_slice($names, 0, 5)) . (count($names) > 5 ? ', ...' : ''),
            self::CONVERSION_HINT
        );
    }

    private static function assertSupportedFormat(string $font): void
    {
        $extension = strtolower(pathinfo($font, PATHINFO_EXTENSION));

        if (in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            return;
        }

        if (isset(self::UNSUPPORTED_EXTENSIONS[$extension])) {
            throw new FontException(sprintf(
                'Cannot compile "%s": %s, unreadable by TCPDF. %s',
                basename($font),
                self::UNSUPPORTED_EXTENSIONS[$extension],
                self::CONVERSION_HINT
            ));
        }

        throw new FontException(sprintf(
            'Cannot compile "%s": unsupported extension ".%s". Expected one of: .%s.',
            basename($font),
            $extension,
            implode(', .', self::SUPPORTED_EXTENSIONS)
        ));
    }

    /**
     * Resolve the TCPDF font type from the file's sfnt signature.
     *
     * Letting TCPDF auto-detect would silently fall through to `Type1` for any
     * signature it does not recognise, so the two flavours it genuinely cannot
     * handle are rejected here with an actionable message instead.
     *
     * @throws FontException for OpenType/CFF and TrueType-collection files
     */
    private static function detectFontType(string $font): string
    {
        $head = (string) file_get_contents($font, false, null, 0, 4);

        if ($head === "OTTO") {
            throw new FontException(sprintf(
                'Cannot compile "%s": it is an OpenType font with CFF outlines ("OTTO"), '
                . 'which TCPDF cannot embed. Use the TrueType build of the face instead. %s',
                basename($font),
                self::CONVERSION_HINT
            ));
        }

        if ($head === "ttcf") {
            throw new FontException(sprintf(
                'Cannot compile "%s": TrueType collections ("ttcf") bundle several faces '
                . 'and TCPDF cannot embed them. Extract the individual faces first.',
                basename($font)
            ));
        }

        return 'TrueTypeUnicode';
    }

    private static function ensureWritableDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new FontException(sprintf('Font cache directory "%s" does not exist and could not be created.', $path));
        }

        if (!is_writable($path)) {
            throw new FontException(sprintf('Font cache directory "%s" is not writable.', $path));
        }
    }

    /** @param null|callable(string):void $onNotice */
    private static function notify(?callable $onNotice, string $message): void
    {
        if ($onNotice !== null) {
            $onNotice($message);
        }
    }
}
