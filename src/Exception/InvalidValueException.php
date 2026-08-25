<?php

namespace Nadar\PdfGenerator\Exception;

use InvalidArgumentException;

/**
 * A value handed to a slot, enum or value object cannot be used.
 *
 * A programming error rather than a runtime condition: a negative corner
 * radius, a `maxLines` of zero, an unknown alignment name. It extends the
 * SPL {@see InvalidArgumentException}, so existing `catch` blocks keep working,
 * and implements {@see PdfGeneratorException}, so catching everything this
 * package throws catches these too.
 */
class InvalidValueException extends InvalidArgumentException implements PdfGeneratorException
{
}
