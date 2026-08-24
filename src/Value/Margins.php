<?php

namespace Nadar\PdfGenerator\Value;

final class Margins
{
    public function __construct(public readonly float $left, public readonly float $top, public readonly float $right, public readonly ?float $bottom = null)
    {
    }

    public function bottom(): float
    {
        return $this->bottom ?? $this->top;
    }
}
