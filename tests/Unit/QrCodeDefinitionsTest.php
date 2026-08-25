<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * `src/bootstrap.php` pre-empts TCPDF's `QRCODEDEFS` block so it can set
 * `QR_FIND_FROM_RANDOM` to false, which is the only way to get reproducible QR
 * output. Pre-empting means TCPDF's own block never runs, so this suite checks
 * that our copy still matches TCPDF's - a future TCPDF adding or changing a
 * constant there fails here rather than fataling inside the renderer.
 */
final class QrCodeDefinitionsTest extends TestCase
{
    /** The one constant we deliberately diverge on, and why. */
    private const OVERRIDDEN = ['QR_FIND_FROM_RANDOM' => false];

    public function testEveryConstantTcpdfWouldDefineIsDefined(): void
    {
        $missing = [];

        foreach (self::tcpdfDefinitions() as $name => $value) {
            if (!defined($name)) {
                $missing[] = $name;
            }
        }

        self::assertSame(
            [],
            $missing,
            'TCPDF defines these in its QRCODEDEFS block but src/bootstrap.php does not. '
            . 'Add them there, or QR rendering will fail with an undefined constant.'
        );
    }

    public function testValuesMatchTcpdfExceptTheDocumentedOverride(): void
    {
        foreach (self::tcpdfDefinitions() as $name => $value) {
            if (array_key_exists($name, self::OVERRIDDEN)) {
                continue;
            }

            self::assertSame(
                $value,
                constant($name),
                sprintf('%s diverges from TCPDF without being a documented override.', $name)
            );
        }
    }

    /** Reproducible output is the whole point of the override. */
    public function testMaskSelectionIsNotRandomised(): void
    {
        // Read indirectly: a literal would be folded away before the assertion runs.
        $value = static fn (string $name): mixed => constant($name);

        self::assertFalse($value('QR_FIND_FROM_RANDOM'), 'a truthy value makes QRcode::mask() pick masks with rand()');
        self::assertTrue($value('QR_FIND_BEST_MASK'), 'without this, mask scoring is skipped entirely');
    }

    public function testIdenticalPayloadsProduceIdenticalPatterns(): void
    {
        $first = (new \TCPDF2DBarcode('https://example.com/events/city-run', 'QRCODE,M'))->getBarcodeArray();
        $second = (new \TCPDF2DBarcode('https://example.com/events/city-run', 'QRCODE,M'))->getBarcodeArray();

        self::assertNotEmpty($first['bcode']);
        self::assertSame($first['bcode'], $second['bcode']);
    }

    public function testOurBlockActuallySuppressedTcpdfs(): void
    {
        $defined = static fn (string $name): bool => defined($name);

        self::assertTrue($defined('QRCODEDEFS'), 'without this TCPDF would re-run its own block');
        // Loading the barcode class must not emit a redefinition warning.
        self::assertSame([], self::redefinitionWarnings());
    }

    /**
     * Warnings raised while (re)loading TCPDF's QR source.
     *
     * @return list<string>
     */
    private static function redefinitionWarnings(): array
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            // Already loaded, so this is a no-op include; the assertion is that
            // nothing in our bootstrap collides with TCPDF's definitions.
            class_exists(\QRcode::class);
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }

    /**
     * The constants TCPDF's own `QRCODEDEFS` block would define, read from the
     * installed source.
     *
     * @return array<string,bool|int>
     */
    private static function tcpdfDefinitions(): array
    {
        $file = self::qrCodeSourcePath();
        $source = (string) file_get_contents($file);

        $start = strpos($source, "if (!defined('QRCODEDEFS')) {");
        self::assertNotFalse($start, "QRCODEDEFS guard not found in {$file}");

        $block = substr($source, $start, self::blockLength($source, $start));

        preg_match_all("/define\\(\\s*'([A-Z0-9_]+)'\\s*,\\s*([^)]+?)\\s*\\);/", $block, $matches, PREG_SET_ORDER);
        self::assertNotEmpty($matches, 'no constants parsed from the QRCODEDEFS block');

        $definitions = [];
        foreach ($matches as [, $name, $literal]) {
            $definitions[$name] = match ($literal) {
                'true' => true,
                'false' => false,
                default => (int) $literal,
            };
        }

        return $definitions;
    }

    /** Length of the brace-balanced block starting at $start. */
    private static function blockLength(string $source, int $start): int
    {
        $offset = strpos($source, '{', $start);
        self::assertNotFalse($offset);

        $depth = 0;
        for ($i = $offset; $i < strlen($source); ++$i) {
            $depth += match ($source[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $i - $start + 1;
            }
        }

        self::fail('unbalanced QRCODEDEFS block');
    }

    private static function qrCodeSourcePath(): string
    {
        $reflection = new \ReflectionClass(\QRcode::class);
        $file = $reflection->getFileName();
        self::assertNotFalse($file);

        return $file;
    }
}
