<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Exception\InvalidValueException;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Rect;

/**
 * A linear (1D) barcode slot - the invoice and logistics counterpart to
 * {@see QrBox}.
 *
 * @see PdfGenerator::barcode1d()
 */
final class BarcodeBox implements Slot
{
    /**
     * @param string     $id        slot name
     * @param Barcode1D  $type      symbology; the payload must be valid for it
     * @param float      $x         left edge in mm
     * @param float      $y         top edge in mm
     * @param float      $w         total width in mm
     * @param float      $h         bar height in mm, excluding any text line
     * @param null|Color $color     bar (and text) colour; `null` means black
     * @param null|Color $background background fill; `null` means transparent
     * @param bool       $showText  print the human-readable line under the bars
     * @param float      $padding   margin around the bars, **in mm** - unlike
     *                              {@see QrBox::$quietZone}, where TCPDF counts modules
     */
    public function __construct(
        public readonly string $id,
        public readonly Barcode1D $type,
        public readonly float $x,
        public readonly float $y,
        public readonly float $w,
        public readonly float $h,
        public readonly ?Color $color = null,
        public readonly ?Color $background = null,
        public readonly bool $showText = false,
        public readonly float $padding = 0.0
    ) {
        if ($w <= 0 || $h <= 0) {
            throw new InvalidValueException(sprintf(
                'BarcodeBox "%s" needs positive dimensions, got %.3f x %.3f mm.',
                $id,
                $w,
                $h
            ));
        }

        if ($padding < 0) {
            throw new InvalidValueException(sprintf('BarcodeBox "%s" padding must not be negative.', $id));
        }
    }

    public function offset(float $dx, float $dy): self
    {
        return new self(
            $this->id,
            $this->type,
            $this->x + $dx,
            $this->y + $dy,
            $this->w,
            $this->h,
            $this->color,
            $this->background,
            $this->showText,
            $this->padding
        );
    }

    public function bounds(): Rect
    {
        return new Rect($this->x, $this->y, $this->w, $this->h);
    }
}
