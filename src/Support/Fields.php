<?php

namespace Nadar\PdfGenerator\Support;

final class Fields
{
    public static function get(array $data, string $key, string $default = ''): string
    {
        if (array_key_exists($key, $data)) {
            return trim((string) $data[$key]);
        }

        $alternate = str_contains($key, '-') ? str_replace('-', '_', $key) : str_replace('_', '-', $key);
        if ($alternate !== $key && array_key_exists($alternate, $data)) {
            return trim((string) $data[$alternate]);
        }

        return $default;
    }

    public static function all(array $data, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($data, $key);
        }

        return $result;
    }
}
