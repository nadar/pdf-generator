<?php

namespace Nadar\PdfGenerator\Value;

/**
 * An axis-aligned rectangle in millimetres, measured from the top-left of the page.
 */
final class Rect
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $w,
        public readonly float $h
    ) {
    }

    /** X coordinate of the right edge. */
    public function right(): float
    {
        return $this->x + $this->w;
    }

    /** Y coordinate of the bottom edge. */
    public function bottom(): float
    {
        return $this->y + $this->h;
    }

    /** @return array{0:float,1:float} the centre point as `[x, y]` */
    public function center(): array
    {
        return [$this->x + $this->w / 2, $this->y + $this->h / 2];
    }

    /** @return array{x:float,y:float,w:float,h:float} */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'w' => $this->w, 'h' => $this->h];
    }
}
