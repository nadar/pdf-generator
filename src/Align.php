<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Exception\InvalidValueException;

/**
 * Horizontal text alignment inside a {@see TextBox}.
 *
 * The backing values are the single-letter codes TCPDF expects, so
 * `Align::Center->value` can be handed to raw TCPDF calls unchanged.
 */
enum Align: string
{
    case Left = 'L';
    case Center = 'C';
    case Right = 'R';
    case Justify = 'J';

    /**
     * Accept either an `Align` case or a legacy `'L'|'C'|'R'|'J'` string.
     *
     * Also understands the spelled-out names (`left`, `center`, `centre`,
     * `right`, `justify`) so layouts loaded from YAML/JSON stay readable.
     *
     * @throws InvalidValueException when the value maps to no alignment
     */
    public static function coerce(self|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'l', 'left' => self::Left,
            'c', 'center', 'centre' => self::Center,
            'r', 'right' => self::Right,
            'j', 'justify', 'justified' => self::Justify,
            default => throw new InvalidValueException(sprintf(
                'Unknown alignment "%s". Use Align::Left, Align::Center, Align::Right or Align::Justify.',
                $value
            )),
        };
    }
}
