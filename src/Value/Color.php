<?php

namespace Nadar\PdfGenerator\Value;

final readonly class Color
{
    /** @param list<float|int> $channels */
    private function __construct(public string $model, public array $channels)
    {
    }

    public static function hex(string $hex): self
    {
        $value = ltrim($hex, '#');
        if (strlen($value) === 3) {
            $value = sprintf('%s%s%s%s%s%s', $value[0], $value[0], $value[1], $value[1], $value[2], $value[2]);
        }

        return self::rgb(
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2))
        );
    }

    public static function rgb(int $r, int $g, int $b): self
    {
        return new self('RGB', [$r, $g, $b]);
    }

    public static function cmyk(float $c, float $m, float $y, float $k): self
    {
        return new self('CMYK', [$c, $m, $y, $k]);
    }

    public static function gray(int|float $gray): self
    {
        return new self('GRAY', [$gray]);
    }
}
