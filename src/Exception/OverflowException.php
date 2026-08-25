<?php

namespace Nadar\PdfGenerator\Exception;

/**
 * Text could not be made to fit a box.
 *
 * Thrown by {@see \Nadar\PdfGenerator\Overflow::Shrink}, and when a box
 * declares an overflow policy without a height. Use
 * {@see \Nadar\PdfGenerator\Overflow::ShrinkThenClip} for data-driven slots,
 * where one unusually long value should not fail the whole render.
 */
class OverflowException extends ConfigurationException
{
}
