<?php

namespace Nadar\PdfGenerator\Value;

final readonly class PageSize
{
    public function __construct(public float $width, public float $height)
    {
    }

    public function orientation(): string
    {
        return $this->width > $this->height ? 'L' : 'P';
    }

    public function asArray(): array
    {
        return [$this->width, $this->height];
    }
}
