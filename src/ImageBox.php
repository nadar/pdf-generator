<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Rect;

/**
 * One immutable image slot: a box, how the image fills it, and what outline it
 * is clipped to.
 *
 * This is the typed replacement for the aspect-ratio arithmetic, the
 * `Circle(..., 'CNZ')` clip and the nineteen-positional-argument `Image()` call
 * that a template overlay otherwise needs:
 *
 * ```php
 * $pdf->image(
 *     ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85, placeholder: Color::hex('#ff920c')),
 *     $event['image'],
 * );
 * ```
 *
 * All coordinates are millimetres from the top-left of the page.
 *
 * @see PdfGenerator::image()
 */
final class ImageBox
{
    public readonly Shape $shape;

    /**
     * @param string      $id          slot name, used in error messages and debug output
     * @param float       $x           left edge in mm
     * @param float       $y           top edge in mm
     * @param float       $w           box width in mm
     * @param float       $h           box height in mm
     * @param Fit         $fit         how the image is scaled into the box
     * @param null|Shape  $shape       clip outline; `null` means {@see Shape::rect()}
     * @param int         $dpi         resolution the image is embedded at. 300 is print
     *                                 default; lowering it shrinks the file at the cost
     *                                 of detail.
     * @param null|Color  $placeholder fill drawn in the box's shape when the source is
     *                                 missing or unreachable. Without it - and without an
     *                                 `$onMissing` callback - a missing image throws.
     * @param float       $rotation    clockwise rotation in degrees around the box centre
     */
    public function __construct(
        public readonly string $id,
        public readonly float $x,
        public readonly float $y,
        public readonly float $w,
        public readonly float $h,
        public readonly Fit $fit = Fit::Cover,
        ?Shape $shape = null,
        public readonly int $dpi = 300,
        public readonly ?Color $placeholder = null,
        public readonly float $rotation = 0.0
    ) {
        if ($w <= 0 || $h <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'ImageBox "%s" needs a positive width and height, got %.3f x %.3f mm.',
                $id,
                $w,
                $h
            ));
        }

        if ($dpi <= 0) {
            throw new \InvalidArgumentException(sprintf('ImageBox "%s" needs a positive dpi, %d given.', $id, $dpi));
        }

        $this->shape = $shape ?? Shape::rect();
    }

    /**
     * A circular slot described the way a design describes it: centre and diameter.
     *
     * Saves the caller the `cx - diameter / 2` conversion, which is where
     * off-by-a-radius mistakes come from.
     */
    public static function circle(
        string $id,
        float $cx,
        float $cy,
        float $diameter,
        Fit $fit = Fit::Cover,
        ?Color $placeholder = null,
        int $dpi = 300
    ): self {
        return new self(
            $id,
            $cx - $diameter / 2,
            $cy - $diameter / 2,
            $diameter,
            $diameter,
            $fit,
            Shape::circle(),
            $dpi,
            $placeholder
        );
    }

    /** A slot with rounded corners. */
    public static function rounded(
        string $id,
        float $x,
        float $y,
        float $w,
        float $h,
        float $radius,
        Fit $fit = Fit::Cover,
        ?Color $placeholder = null,
        int $dpi = 300
    ): self {
        return new self($id, $x, $y, $w, $h, $fit, Shape::roundRect($radius), $dpi, $placeholder);
    }

    /** Copy shifted by ($dx, $dy) mm - the repeated-row building block. */
    public function offset(float $dx, float $dy): self
    {
        return new self(
            $this->id,
            $this->x + $dx,
            $this->y + $dy,
            $this->w,
            $this->h,
            $this->fit,
            $this->shape,
            $this->dpi,
            $this->placeholder,
            $this->rotation
        );
    }

    /** The declared box. */
    public function bounds(): Rect
    {
        return new Rect($this->x, $this->y, $this->w, $this->h);
    }

    /**
     * The drawn size for an image of the given pixel dimensions, honouring {@see $fit}.
     *
     * Exposed because it is useful in tests and calibration scripts; the
     * renderer uses it internally.
     *
     * @return array{0:float,1:float,2:float,3:float} `[x, y, w, h]` in mm; may extend
     *         beyond the box for {@see Fit::Cover}, which is what the clip then trims
     */
    public function placement(int $pixelWidth, int $pixelHeight): array
    {
        if ($this->fit === Fit::Stretch || $pixelWidth <= 0 || $pixelHeight <= 0) {
            return [$this->x, $this->y, $this->w, $this->h];
        }

        $sourceRatio = $pixelWidth / $pixelHeight;
        $boxRatio = $this->w / $this->h;

        // Cover matches the axis that would otherwise leave a gap; contain
        // matches the axis that would otherwise overflow.
        $matchWidth = $this->fit === Fit::Cover
            ? $sourceRatio < $boxRatio
            : $sourceRatio > $boxRatio;

        if ($matchWidth) {
            $w = $this->w;
            $h = $this->w / $sourceRatio;
        } else {
            $h = $this->h;
            $w = $this->h * $sourceRatio;
        }

        return [
            $this->x + ($this->w - $w) / 2,
            $this->y + ($this->h - $h) / 2,
            $w,
            $h,
        ];
    }
}
