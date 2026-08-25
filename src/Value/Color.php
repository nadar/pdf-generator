<?php

namespace Nadar\PdfGenerator\Value;

use Nadar\PdfGenerator\Exception\ConfigurationException;

/**
 * A colour in one of the three models TCPDF understands.
 *
 * `toArray()` returns the channel list in exactly the shape TCPDF's
 * `Set*ColorArray()` and barcode `style` arrays expect, so a `Color` can be
 * handed to raw TCPDF calls without unpacking it by hand:
 *
 * ```php
 * $pdf->raw()->SetFillColorArray(Color::hex('#223764')->toArray());
 * ```
 */
final class Color
{
    public const MODEL_RGB = 'RGB';
    public const MODEL_CMYK = 'CMYK';
    public const MODEL_GRAY = 'GRAY';

    /**
     * @param string           $model    one of `RGB`, `CMYK`, `GRAY`
     * @param list<float|int>  $channels 3 channels for RGB (0-255), 4 for CMYK (0-100),
     *                                   1 for GRAY (0-255)
     */
    private function __construct(public readonly string $model, public readonly array $channels)
    {
    }

    /**
     * Parse a CSS-style hex colour: `#223764`, `223764`, `#abc` or `abc`.
     *
     * @throws ConfigurationException on anything that is not 3 or 6 hex digits
     */
    public static function hex(string $hex): self
    {
        $value = ltrim(trim($hex), '#');

        if (!preg_match('/^(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            throw new ConfigurationException(sprintf(
                'Invalid hex colour "%s". Expected 3 or 6 hex digits, e.g. "#223764".',
                $hex
            ));
        }

        if (strlen($value) === 3) {
            $value = sprintf('%s%s%s%s%s%s', $value[0], $value[0], $value[1], $value[1], $value[2], $value[2]);
        }

        return self::rgb(
            (int) hexdec(substr($value, 0, 2)),
            (int) hexdec(substr($value, 2, 2)),
            (int) hexdec(substr($value, 4, 2))
        );
    }

    /** @param int $r 0-255 @param int $g 0-255 @param int $b 0-255 */
    public static function rgb(int $r, int $g, int $b): self
    {
        return new self(self::MODEL_RGB, [
            self::clamp($r, 0, 255),
            self::clamp($g, 0, 255),
            self::clamp($b, 0, 255),
        ]);
    }

    /**
     * Process colour, each channel 0-100.
     *
     * Prefer this over RGB for anything going to an offset press.
     */
    public static function cmyk(float $c, float $m, float $y, float $k): self
    {
        return new self(self::MODEL_CMYK, [
            self::clampFloat($c),
            self::clampFloat($m),
            self::clampFloat($y),
            self::clampFloat($k),
        ]);
    }

    /** @param float|int $gray 0 (black) to 255 (white) */
    public static function gray(int|float $gray): self
    {
        return new self(self::MODEL_GRAY, [self::clamp($gray, 0, 255)]);
    }

    /** Opaque black. */
    public static function black(): self
    {
        return self::gray(0);
    }

    /** Opaque white. */
    public static function white(): self
    {
        return self::gray(255);
    }

    /**
     * The channel list, ready for TCPDF's `Set*ColorArray()` and barcode styles.
     *
     * @return list<float|int>
     */
    public function toArray(): array
    {
        return $this->channels;
    }

    /**
     * This colour as 8-bit RGB, converting from CMYK/GRAY when needed.
     *
     * The CMYK conversion is the naive one and ignores any ICC profile - use it
     * for on-screen previews and debug output, not for colour-managed output.
     *
     * @return array{0:int,1:int,2:int}
     */
    public function rgb255(): array
    {
        return match ($this->model) {
            self::MODEL_RGB => [
                (int) round((float) $this->channels[0]),
                (int) round((float) $this->channels[1]),
                (int) round((float) $this->channels[2]),
            ],
            self::MODEL_GRAY => array_fill(0, 3, (int) round((float) $this->channels[0])),
            default => self::cmykToRgb(
                (float) $this->channels[0],
                (float) $this->channels[1],
                (float) $this->channels[2],
                (float) $this->channels[3]
            ),
        };
    }

    /** Uppercase `#RRGGBB`, via {@see rgb255()}. */
    public function toHex(): string
    {
        [$r, $g, $b] = $this->rgb255();

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function cmykToRgb(float $c, float $m, float $y, float $k): array
    {
        $scale = static fn (float $channel): int => (int) round(
            255 * (1 - min(1.0, $channel / 100)) * (1 - min(1.0, $k / 100))
        );

        return [$scale($c), $scale($m), $scale($y)];
    }

    private static function clamp(int|float $value, int|float $min, int|float $max): int|float
    {
        return max($min, min($max, $value));
    }

    /** Percentage channel, always a float so CMYK arrays stay homogeneous. */
    private static function clampFloat(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }
}
