<?php

namespace Nadar\PdfGenerator\Value;

use Nadar\PdfGenerator\Overflow;

/**
 * The resolved geometry of a text write, in millimetres and points.
 *
 * Returned by {@see \Nadar\PdfGenerator\PdfGenerator::probe()} (without drawing
 * anything) and by {@see \Nadar\PdfGenerator\PdfGenerator::lastMetrics()} (after
 * a write). This is what closes the calibration loop in-process: render, read
 * the numbers back, correct the constants - no external PDF tooling needed.
 */
final class TextMetrics
{
    /**
     * @param Rect     $box       the cell box actually handed to TCPDF, after anchor
     *                            translation and any shrink
     * @param float    $baseline  y of the first baseline in mm
     * @param float    $size      the font size actually used, in pt (differs from the
     *                            requested size when an overflow policy shrank it)
     * @param int      $lines     number of rendered lines
     * @param float    $lineHeight height of one line in mm (`size` in mm * cellHeightRatio)
     * @param float    $height    total measured text height in mm
     * @param float    $ascent    font ascent at `size`, in mm
     * @param float    $descent   font descent at `size`, in mm (positive)
     * @param bool     $fits      whether the text fit the declared height
     * @param Overflow $overflow  the policy that was applied
     */
    public function __construct(
        public readonly Rect $box,
        public readonly float $baseline,
        public readonly float $size,
        public readonly int $lines,
        public readonly float $lineHeight,
        public readonly float $height,
        public readonly float $ascent,
        public readonly float $descent,
        public readonly bool $fits,
        public readonly Overflow $overflow
    ) {
    }

    /** Y of the top of the capitals on the first line, in mm. */
    public function capTop(float $capHeight): float
    {
        return $this->baseline - $capHeight;
    }

    /** Baseline of the n-th line (0-based), in mm. */
    public function baselineOf(int $line): float
    {
        return $this->baseline + $line * $this->lineHeight;
    }

    /**
     * Flat representation, handy for logging a calibration run.
     *
     * @return array{x:float,y:float,w:float,h:float,baseline:float,size:float,lines:int,lineHeight:float,height:float,ascent:float,descent:float,fits:bool,overflow:string}
     */
    public function toArray(): array
    {
        return [
            'x' => $this->box->x,
            'y' => $this->box->y,
            'w' => $this->box->w,
            'h' => $this->box->h,
            'baseline' => $this->baseline,
            'size' => $this->size,
            'lines' => $this->lines,
            'lineHeight' => $this->lineHeight,
            'height' => $this->height,
            'ascent' => $this->ascent,
            'descent' => $this->descent,
            'fits' => $this->fits,
            'overflow' => $this->overflow->name,
        ];
    }
}
