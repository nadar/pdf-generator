<?php

namespace Nadar\PdfGenerator\Value;

/** A page's dimensions in millimetres. */
final class PageSize
{
    /** @param float $width mm @param float $height mm */
    public function __construct(public readonly float $width, public readonly float $height)
    {
    }

    /** `L` when wider than tall, otherwise `P`. */
    public function orientation(): string
    {
        return $this->width > $this->height ? 'L' : 'P';
    }

    /**
     * As TCPDF's `[width, height]` format argument.
     *
     * @return list<float>
     */
    public function asArray(): array
    {
        return [$this->width, $this->height];
    }
}
