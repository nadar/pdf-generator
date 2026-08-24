<?php

namespace Nadar\PdfGenerator\Value;

final readonly class Margins
{
    public function __construct(public float $left, public float $top, public float $right, public ?float $bottom = null)
    {
    }

    public function bottom(): float
    {
        return $this->bottom ?? $this->top;
    }
}
