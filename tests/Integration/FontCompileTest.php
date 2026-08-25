<?php

namespace Nadar\PdfGenerator\Tests\Integration;

use Nadar\PdfGenerator\Exception\FontCacheMissingException;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\PdfGenerator;
use Nadar\PdfGenerator\Support\FontCompiler;
use Nadar\PdfGenerator\Tests\Support\TestSettings;
use Nadar\PdfGenerator\TextBox;

/**
 * End-to-end font compilation against a real font file.
 *
 * The package ships no font binaries, so these tests are skipped unless a
 * `.ttf` is present in `tests/Fixtures/fonts` - see the README there. Path and
 * key derivation are covered without a font by
 * {@see \Nadar\PdfGenerator\Tests\Unit\FontCompilerTest}.
 */
final class FontCompileTest extends IntegrationTestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = dirname(__DIR__) . '/Fixtures/fonts';
        $candidates = glob($fixtures . '/*.ttf') ?: [];

        if ($candidates === []) {
            self::markTestSkipped('No .ttf in tests/Fixtures/fonts; see the README there.');
        }

        $this->source = $candidates[0];
    }

    /**
     * The reported bug, end to end.
     *
     * TCPDF builds its output filename as `$outpath . $key . '.php'`, so a cache
     * path without a trailing separator used to write `.../cache<key>.php` while
     * the registry looked in `.../cache/`. The build reported success and the
     * first render then reported a missing cache.
     */
    public function testCachePathWithoutTrailingSeparatorStillWritesIntoTheDirectory(): void
    {
        $cache = $this->workspace . '/cache';
        mkdir($cache);

        $key = FontCompiler::compile($this->source, $cache);

        self::assertFileExists($cache . '/' . $key . '.php', 'the definition lands inside the cache directory');
        self::assertFileDoesNotExist($this->workspace . '/cache' . $key . '.php', 'and not beside it');
        self::assertSame($cache . DIRECTORY_SEPARATOR . $key . '.php', FontCompiler::cacheFile($cache, $key));
    }

    public function testTrailingSeparatorProducesTheSameResult(): void
    {
        $withSlash = $this->workspace . '/with/';
        $withoutSlash = $this->workspace . '/without';
        mkdir($withSlash, 0o777, true);
        mkdir($withoutSlash, 0o777, true);

        $a = FontCompiler::compile($this->source, $withSlash);
        $b = FontCompiler::compile($this->source, $withoutSlash);

        self::assertSame($a, $b);
        self::assertFileExists($withSlash . $a . '.php');
        self::assertFileExists($withoutSlash . '/' . $b . '.php');
    }

    /**
     * TCPDF opens its output through a "file://" wrapper, which cannot resolve a
     * relative path - so `--cache=examples/assets/fonts/cache` used to die inside
     * TCPDF with "Remote host file access not supported".
     */
    public function testRelativeCachePathWorks(): void
    {
        $previous = getcwd();
        self::assertNotFalse($previous);

        try {
            chdir($this->workspace);

            $key = FontCompiler::compile($this->source, 'nested/cache');

            self::assertFileExists($this->workspace . '/nested/cache/' . $key . '.php');
        } finally {
            chdir($previous);
        }
    }

    /** The key the build writes must be the key the registry looks for. */
    public function testCompiledKeyMatchesKeyFor(): void
    {
        $cache = $this->workspace . '/cache';
        mkdir($cache);

        self::assertSame(
            FontCompiler::keyFor(basename($this->source)),
            FontCompiler::compile($this->source, $cache)
        );
    }

    public function testCompileDirectoryCompilesEveryFace(): void
    {
        $fonts = $this->workspace . '/fonts';
        $cache = $this->workspace . '/cache';
        mkdir($fonts);
        copy($this->source, $fonts . '/Brand-Regular.ttf');
        copy($this->source, $fonts . '/Brand-Bold.ttf');

        $compiled = FontCompiler::compileDirectory($fonts, $cache);

        self::assertSame(['Brand-Bold.ttf' => 'brandb', 'Brand-Regular.ttf' => 'brand'], $compiled);
        self::assertFileExists($cache . '/brand.php');
        self::assertFileExists($cache . '/brandb.php');
    }

    /** The cache directory is created rather than demanded to exist first. */
    public function testCompileDirectoryCreatesTheCacheDirectory(): void
    {
        $fonts = $this->workspace . '/fonts';
        mkdir($fonts);
        copy($this->source, $fonts . '/Brand-Regular.ttf');

        FontCompiler::compileDirectory($fonts, $this->workspace . '/nested/cache');

        self::assertDirectoryExists($this->workspace . '/nested/cache');
    }

    /** TCPDF returns early for an existing definition, so --force must remove it first. */
    public function testForceRebuildsAnExistingDefinition(): void
    {
        $cache = $this->workspace . '/cache';
        mkdir($cache);

        $key = FontCompiler::compile($this->source, $cache);
        $file = $cache . '/' . $key . '.php';
        file_put_contents($file, "<?php // stale\n");

        FontCompiler::compile($this->source, $cache);
        self::assertStringContainsString('stale', (string) file_get_contents($file), 'without force the stale file survives');

        FontCompiler::compile($this->source, $cache, force: true);
        self::assertStringNotContainsString('stale', (string) file_get_contents($file));
    }

    /** A compiled face must actually be usable for rendering. */
    public function testCompiledFontRendersAndIsEmbedded(): void
    {
        $fonts = $this->workspace . '/fonts';
        $cache = $this->workspace . '/cache';
        mkdir($fonts);
        copy($this->source, $fonts . '/Brand-Regular.ttf');
        FontCompiler::compileDirectory($fonts, $cache);

        $settings = new class ($fonts, $cache) extends \Nadar\PdfGenerator\AbstractPdfSettings {
            public function __construct(private string $fonts, private string $cache)
            {
            }

            public function fontPath(): string
            {
                return $this->fonts;
            }

            public function fontCachePath(): string
            {
                // deliberately no trailing separator
                return $this->cache;
            }

            public function templatePath(): string
            {
                return $this->fonts;
            }

            public function fonts(): FontSet
            {
                return FontSet::make()->family('brand', 'Brand-Regular.ttf')->role('regular', 'brand');
            }
        };

        $pdf = new PdfGenerator($settings);
        $pdf->page(null, 'A4');
        $pdf->write(new TextBox('t', 20, 20, 150, size: 18.0), 'Embedded brand font');
        $bytes = $pdf->bytes();

        self::assertStringContainsString('/FontFile2', $bytes, 'the face is embedded, not substituted');
    }

    /** The failure a missing build produces must name the file and the command. */
    public function testMissingDefinitionExplainsHowToBuildIt(): void
    {
        $fonts = $this->workspace . '/fonts';
        mkdir($fonts);
        copy($this->source, $fonts . '/Brand-Regular.ttf');

        $settings = new TestSettings(
            $this->workspace,
            FontSet::make()->family('brand', 'Brand-Regular.ttf')->role('regular', 'brand')
        );

        $this->expectException(FontCacheMissingException::class);
        $this->expectExceptionMessageMatches('/No compiled definition for brand\/regular.*fonts:build/s');
        new PdfGenerator($settings);
    }
}
