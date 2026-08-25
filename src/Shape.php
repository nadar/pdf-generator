<?php

namespace Nadar\PdfGenerator;

/**
 * The outline an image is clipped to.
 *
 * A value object rather than a plain enum because `roundRect()` carries a
 * corner radius.
 */
final class Shape
{
    private function __construct(
        public readonly ShapeKind $kind,
        public readonly float $radius = 0.0
    ) {
    }

    /** No clipping - the image fills its rectangular box. */
    public static function rect(): self
    {
        return new self(ShapeKind::Rect);
    }

    /**
     * Clip to the ellipse inscribed in the box.
     *
     * With a square box this is a circle, which is the common "round avatar"
     * case; with a non-square box it is an ellipse.
     */
    public static function circle(): self
    {
        return new self(ShapeKind::Circle);
    }

    /**
     * Clip to a rectangle with rounded corners.
     *
     * @param float $radius corner radius in mm; clamped to half the shorter side
     *
     * @throws \InvalidArgumentException when $radius is negative
     */
    public static function roundRect(float $radius): self
    {
        if ($radius < 0) {
            throw new \InvalidArgumentException(sprintf('Corner radius must not be negative, %.3f given.', $radius));
        }

        return new self(ShapeKind::RoundRect, $radius);
    }
}
