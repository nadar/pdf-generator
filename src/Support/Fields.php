<?php

namespace Nadar\PdfGenerator\Support;

/**
 * Reads slot values out of a plain data array.
 *
 * Backs {@see \Nadar\PdfGenerator\PdfGenerator::writeAll()}.
 */
final class Fields
{
    /**
     * Read one field as a trimmed string.
     *
     * `-` and `_` are interchangeable in the key, so a layout slot named
     * `event-title` also matches a data key `event_title`. A missing key yields
     * $default rather than an error: a half-filled document is easier to debug
     * than an exception in the middle of a render.
     *
     * @param array<string,mixed> $data
     *
     * @throws \Nadar\PdfGenerator\Exception\ConfigurationException when the value
     *         is neither scalar nor {@see \Stringable}
     */
    public static function get(array $data, string $key, string $default = ''): string
    {
        if (array_key_exists($key, $data)) {
            return trim(Cast::toString($data[$key], $key));
        }

        $alternate = str_contains($key, '-') ? str_replace('-', '_', $key) : str_replace('_', '-', $key);
        if ($alternate !== $key && array_key_exists($alternate, $data)) {
            return trim(Cast::toString($data[$alternate], $alternate));
        }

        return $default;
    }

    /**
     * Read many fields at once, keyed as requested.
     *
     * @param array<string,mixed> $data
     * @param iterable<string>    $keys
     *
     * @return array<string,string>
     */
    public static function all(array $data, iterable $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($data, $key);
        }

        return $result;
    }
}
