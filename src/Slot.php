<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Value\Rect;

/**
 * One positioned element of a {@see Layout}.
 *
 * A fixed layout is rarely text alone: a row is a headline, a meta line, a photo
 * and a code, all moving together. Implementing this lets every one of them live
 * in the same `Layout`, so {@see Layout::repeat()} shifts the whole row and the
 * `$index * $pitch` arithmetic disappears from the render loop.
 *
 * Coordinates are millimetres from the top-left of the page.
 *
 * @see TextBox
 * @see ImageBox
 * @see QrBox
 * @see BarcodeBox
 */
interface Slot
{
    /** Slot name; unique within a {@see Layout}, and the data key for text slots. */
    public string $id { get; }

    /** A copy shifted by ($dx, $dy) mm. */
    public function offset(float $dx, float $dy): self;

    /**
     * The area the slot occupies.
     *
     * For a {@see TextBox} with no declared height this is the box at zero
     * height, since the real extent depends on the text; use
     * {@see PdfGenerator::probe()} for that.
     */
    public function bounds(): Rect;
}
