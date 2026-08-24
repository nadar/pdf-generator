<?php

namespace Nadar\PdfGenerator\Value;

final class PageSize
{
    public function __construct(public readonly float $width, public readonly float $height)
    {
    }

    public function orientation(): string
    {
        return $this->width > $this->height ? 'L' : 'P';
    }

    /** @return list<float> */
    public function asArray(): array
    {
        return [$this->width, $this->height];
    }
}
