<?php

namespace Nadar\PdfGenerator\Tests\Support;

use Nadar\PdfGenerator\Tcpdf\MetricsFpdi;

/**
 * The reason `pdfFactory()` exists: `Header()`/`Footer()` can only be provided
 * by a TCPDF subclass, and PdfGenerator is final.
 */
final class HeaderPdf extends MetricsFpdi
{
    public const HEADER_TEXT = 'CUSTOM HEADER';

    /**
     * TCPDF declares this without a return type; narrowing to void in the
     * override is allowed and keeps the signature honest.
     */
    public function Header(): void
    {
        $this->SetFont('helvetica', '', 8);
        $this->Text(10, 5, self::HEADER_TEXT);
    }
}
