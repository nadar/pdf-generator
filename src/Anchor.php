<?php

namespace Nadar\PdfGenerator;

/**
 * What the `y` coordinate of a {@see TextBox} refers to.
 *
 * Design tools hand over *ink* positions - a baseline from InDesign, or the
 * top of the capitals measured off a reference PDF. TCPDF, on the other hand,
 * positions the *cell* that contains the first line. `Anchor` converts between
 * the two so measured design coordinates can be used verbatim.
 *
 * With `cellHeight = fontSize(mm) * cellHeightRatio`, the first baseline of a
 * box drawn at cell-top `y` sits at:
 *
 *     baseline = y + (cellHeight - ascent - descent) / 2 + ascent
 *
 * @see PdfGenerator::probe() to read the resolved geometry back out
 */
enum Anchor
{
    /** `y` is the top edge of the first line's cell. TCPDF's native behaviour. */
    case Top;

    /** `y` is the first baseline - what a designer means by "text sits here". */
    case Baseline;

    /** `y` is the top of the capital letters (cap height) of the first line. */
    case CapHeight;
}
