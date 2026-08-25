<?php

namespace Nadar\PdfGenerator\Tests\Support;

use Nadar\PdfGenerator\Contract\PdfFactoryInterface;
use setasign\Fpdi\Tcpdf\Fpdi;

final class HeaderPdfFactory implements PdfFactoryInterface
{
    public int $calls = 0;

    public function create(string $orientation, string $unit, string|array $format): Fpdi
    {
        ++$this->calls;

        return new HeaderPdf($orientation, $unit, $format);
    }
}
