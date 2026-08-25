<?php

namespace Nadar\PdfGenerator\Tests\Support;

use Nadar\PdfGenerator\Contract\PdfFactoryInterface;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * A factory returning a bare Fpdi, i.e. one that cannot report cap height.
 *
 * Stands in for a consumer who subclasses `Fpdi` directly instead of
 * {@see \Nadar\PdfGenerator\Tcpdf\MetricsFpdi}.
 */
final class PlainFpdiFactory implements PdfFactoryInterface
{
    public int $calls = 0;

    public function create(string $orientation, string $unit, string|array $format): Fpdi
    {
        ++$this->calls;

        return new Fpdi($orientation, $unit, $format);
    }
}
