<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Exception\FontException;
use Nadar\PdfGenerator\Support\FontCompiler;
use PHPUnit\Framework\TestCase;

final class FontCompilerTest extends TestCase
{
    public function testKeyDerivationForBoldFace(): void
    {
        self::assertSame('brandb', FontCompiler::keyFor('Brand-Bold.ttf'));
        self::assertSame('brand', FontCompiler::keyFor('Brand-Regular.ttf'));
        self::assertSame('brandbi', FontCompiler::keyFor('Brand-BoldItalic.ttf'));
    }

    /**
     * The key must match TCPDF's own derivation exactly, or the build writes one
     * filename and the renderer looks for another.
     *
     * Reference: TCPDF_FONTS::addTTFfont() lowercases the filename, strips
     * everything outside [a-z0-9_], then replaces bold/oblique/italic/regular
     * with b/i/i/'' in a single pass.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tcpdfNameCases')]
    public function testKeyMatchesTcpdfDerivation(string $file, string $expected): void
    {
        self::assertSame($expected, FontCompiler::keyFor($file), $file);
        self::assertSame(self::tcpdfFontName($file), FontCompiler::keyFor($file), $file);
    }

    /** @return iterable<string,array{0:string,1:string}> */
    public static function tcpdfNameCases(): iterable
    {
        yield 'plain' => ['Inter.ttf', 'inter'];
        yield 'regular is stripped' => ['Inter-Regular.ttf', 'inter'];
        yield 'bold' => ['Inter-Bold.ttf', 'interb'];
        yield 'italic' => ['Inter-Italic.ttf', 'interi'];
        yield 'oblique counts as italic' => ['Roboto-Oblique.ttf', 'robotoi'];
        yield 'bold italic' => ['Inter-BoldItalic.ttf', 'interbi'];
        // Order matters: TCPDF replaces "bold" before "italic", so the reversed
        // spelling yields "ib" rather than "bi".
        yield 'italic bold keeps source order' => ['Inter-ItalicBold.ttf', 'interib'];
        yield 'digits survive' => ['Brother-1816-Regular.ttf', 'brother1816'];
        yield 'underscores survive' => ['my_font_Bold.ttf', 'my_font_b'];
        yield 'otf' => ['Inter-Bold.otf', 'interb'];
        // Nothing left after stripping: TCPDF falls back to a generic name, and
        // an empty key would make the cache lookup search for ".php".
        yield 'nothing left falls back' => ['Regular.ttf', 'tcpdffont'];
    }

    public function testNormalizePathAlwaysEndsInOneSeparator(): void
    {
        $expected = '/fonts/cache' . DIRECTORY_SEPARATOR;

        self::assertSame($expected, FontCompiler::normalizePath('/fonts/cache'));
        self::assertSame($expected, FontCompiler::normalizePath('/fonts/cache/'));
        self::assertSame($expected, FontCompiler::normalizePath('/fonts/cache///'));
    }

    /**
     * The reported failure mode: TCPDF concatenates the output path with the key
     * and no separator, so a path without a trailing separator writes
     * "/fonts/cachebrand.php" while the registry looks in "/fonts/cache/".
     */
    public function testCacheFileIsIdenticalWithAndWithoutTrailingSeparator(): void
    {
        $expected = '/app/fonts/cache' . DIRECTORY_SEPARATOR . 'brand.php';

        self::assertSame($expected, FontCompiler::cacheFile('/app/fonts/cache', 'brand'));
        self::assertSame($expected, FontCompiler::cacheFile('/app/fonts/cache/', 'brand'));
    }

    public function testCompileRejectsWebFontFormatsWithAConversionHint(): void
    {
        $dir = self::tempDir();
        file_put_contents($dir . '/Brand-Regular.woff2', 'not really a font');

        try {
            $this->expectException(FontException::class);
            $this->expectExceptionMessageMatches('/woff2.*fontTools/s');
            FontCompiler::compile($dir . '/Brand-Regular.woff2', $dir);
        } finally {
            self::removeDir($dir);
        }
    }

    /** A directory of nothing but web fonts must fail, not silently succeed. */
    public function testCompileDirectoryFailsOnWebFontsOnly(): void
    {
        $dir = self::tempDir();
        foreach (['Brand-Regular.woff', 'Brand-Bold.woff2'] as $name) {
            file_put_contents($dir . '/' . $name, 'x');
        }

        try {
            $this->expectException(FontException::class);
            $this->expectExceptionMessageMatches('/No usable font files.*2 file\(s\).*fontTools/s');
            FontCompiler::compileDirectory($dir, $dir);
        } finally {
            self::removeDir($dir);
        }
    }

    public function testCompileDirectoryFailsOnEmptyDirectory(): void
    {
        $dir = self::tempDir();

        try {
            $this->expectException(FontException::class);
            $this->expectExceptionMessageMatches('/No font files found/');
            FontCompiler::compileDirectory($dir, $dir);
        } finally {
            self::removeDir($dir);
        }
    }

    /** Two sources mapping to one key would overwrite each other silently. */
    public function testCompileDirectoryDetectsKeyCollisions(): void
    {
        $dir = self::tempDir();
        file_put_contents($dir . '/Inter.ttf', 'x');
        file_put_contents($dir . '/Inter-Regular.ttf', 'x');

        try {
            $this->expectException(FontException::class);
            $this->expectExceptionMessageMatches('/both compile to key "inter"/');
            FontCompiler::compileDirectory($dir, $dir);
        } finally {
            self::removeDir($dir);
        }
    }

    public function testCompileDirectoryFailsOnMissingDirectory(): void
    {
        $this->expectException(FontException::class);
        $this->expectExceptionMessageMatches('/does not exist/');
        FontCompiler::compileDirectory('/definitely/not/here', sys_get_temp_dir());
    }

    /** Mirrors TCPDF_FONTS::addTTFfont()'s font-name derivation. */
    private static function tcpdfFontName(string $file): string
    {
        $name = strtolower(pathinfo($file, PATHINFO_FILENAME));
        $name = (string) preg_replace('/[^a-z0-9_]/', '', $name);
        $name = str_replace(['bold', 'oblique', 'italic', 'regular'], ['b', 'i', 'i', ''], $name);

        return $name === '' ? 'tcpdffont' : $name;
    }

    private static function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/pdfgen-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o777, true);

        return $dir;
    }

    private static function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? self::removeDir($file) : unlink($file);
        }
        @rmdir($dir);
    }
}
