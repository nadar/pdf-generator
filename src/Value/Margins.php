<?php

namespace Nadar\PdfGenerator\Value;

/**
 * Page margins in millimetres.
 *
 * Template overlays normally want all zeroes, so that coordinates measured off
 * the design are page coordinates.
 */
final class Margins
{
    /** @param null|float $bottom defaults to $top when omitted */
    public function __construct(public readonly float $left, public readonly float $top, public readonly float $right, public readonly ?float $bottom = null)
    {
    }

    /** The bottom margin, falling back to the top one. */
    public function bottom(): float
    {
        return $this->bottom ?? $this->top;
    }
}
