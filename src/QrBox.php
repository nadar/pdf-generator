<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Exception\InvalidValueException;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Rect;

/**
 * A QR code slot: where it goes and how it looks, separate from what it encodes.
 *
 * Declaring it as a slot keeps it in the same {@see Layout} as the row's text
 * and image, so all of them move together:
 *
 * ```php
 * $row = Layout::make(
 *     new TextBox('title', x: 53.2, y: 58.48, w: 120, h: 11.5),
 *     new QrBox('link', x: 179, y: 58.63, size: 19.5, color: Color::hex('#223764')),
 * );
 *
 * foreach ($row->repeat(times: 6, dy: 40.3) as $index => $slots) {
 *     $pdf->qr($slots->qr('link'), $events[$index]['url']);
 * }
 * ```
 *
 * @see PdfGenerator::qr()
 */
final class QrBox implements Slot
{
    /**
     * @param string     $id        slot name
     * @param float      $x         left edge in mm
     * @param float      $y         top edge in mm
     * @param float      $size      width and height of the square, in mm
     * @param null|Color $color     module colour; `null` means black
     * @param null|Color $background background fill; `null` means transparent, which is
     *                              what lets the code sit on artwork rather than punching
     *                              a white tile through it
     * @param EccLevel   $level     error correction; `M` is the right trade-off around
     *                              20 mm with a full URL
     * @param int        $quietZone margin in **barcode modules** (TCPDF's "auto" is 4).
     *                              Zero lets the design's own whitespace serve as the
     *                              quiet zone, so the code fills the measured box exactly.
     */
    public function __construct(
        public readonly string $id,
        public readonly float $x,
        public readonly float $y,
        public readonly float $size,
        public readonly ?Color $color = null,
        public readonly ?Color $background = null,
        public readonly EccLevel $level = EccLevel::M,
        public readonly int $quietZone = 0
    ) {
        if ($size <= 0) {
            throw new InvalidValueException(sprintf('QrBox "%s" needs a positive size, %.3f given.', $id, $size));
        }

        if ($quietZone < 0) {
            throw new InvalidValueException(sprintf('QrBox "%s" quiet zone must not be negative, %d given.', $id, $quietZone));
        }
    }

    /** A square slot described by its centre, as a design usually measures it. */
    public static function centered(
        string $id,
        float $cx,
        float $cy,
        float $size,
        ?Color $color = null,
        ?Color $background = null,
        EccLevel $level = EccLevel::M,
        int $quietZone = 0
    ): self {
        return new self($id, $cx - $size / 2, $cy - $size / 2, $size, $color, $background, $level, $quietZone);
    }

    public function offset(float $dx, float $dy): self
    {
        return new self(
            $this->id,
            $this->x + $dx,
            $this->y + $dy,
            $this->size,
            $this->color,
            $this->background,
            $this->level,
            $this->quietZone
        );
    }

    public function bounds(): Rect
    {
        return new Rect($this->x, $this->y, $this->size, $this->size);
    }
}
