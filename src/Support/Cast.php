<?php

namespace Nadar\PdfGenerator\Support;

use Nadar\PdfGenerator\Exception\ConfigurationException;
use Stringable;

/**
 * Strict scalar coercion for values coming out of array-defined layouts.
 *
 * Every failure names the offending key, so a bad layout file points straight
 * at the field that needs fixing.
 */
final class Cast
{
    /**
     * @param string $context the field name, used in the error message
     *
     * @throws ConfigurationException when $value is neither scalar nor {@see Stringable}
     */
    public static function toString(mixed $value, string $context): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new ConfigurationException(sprintf('Value for "%s" must be stringable, %s given.', $context, get_debug_type($value)));
    }

    /**
     * @param string $context the field name, used in the error message
     *
     * @throws ConfigurationException when $value is not numeric
     */
    public static function toFloat(mixed $value, string $context): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        throw new ConfigurationException(sprintf('Value for "%s" must be numeric, %s given.', $context, get_debug_type($value)));
    }

    /**
     * @param string $context the field name, used in the error message
     *
     * @throws ConfigurationException when $value cannot be read as a boolean
     */
    public static function toBool(mixed $value, string $context): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? trim($value) !== '';
        }

        throw new ConfigurationException(sprintf('Value for "%s" must be a boolean, %s given.', $context, get_debug_type($value)));
    }
}
