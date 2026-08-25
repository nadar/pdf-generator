<?php

namespace Nadar\PdfGenerator\Contract;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Creates the underlying FPDI/TCPDF document.
 *
 * {@see \Nadar\PdfGenerator\PdfGenerator} is `final`, so this is the seam for
 * behaviour that only a TCPDF subclass can provide: `Header()`/`Footer()`
 * overrides, custom error handling, or reaching TCPDF's protected internals.
 *
 * Return it from {@see PdfSettingsInterface::pdfFactory()}:
 *
 * ```php
 * final class LetterheadFactory implements PdfFactoryInterface
 * {
 *     public function create(string $orientation, string $unit, string|array $format): Fpdi
 *     {
 *         return new LetterheadPdf($orientation, $unit, $format);
 *     }
 * }
 * ```
 *
 * Extend {@see \Nadar\PdfGenerator\Tcpdf\MetricsFpdi} in the returned class to
 * keep `Anchor::CapHeight` working.
 */
interface PdfFactoryInterface
{
    /**
     * @param string            $orientation `P` or `L`
     * @param string            $unit        always `mm` - the package works in millimetres
     * @param string|list<float> $format     a named format like `A4`, or `[width, height]` in mm
     */
    public function create(string $orientation, string $unit, string|array $format): Fpdi;
}
