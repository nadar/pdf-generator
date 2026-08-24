<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Contract\PdfSettingsInterface;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Margins;

abstract class AbstractPdfSettings implements PdfSettingsInterface
{
    /** @return string|list<float> */
    public function pageFormat(): string|array
    {
        return 'A4';
    }

    public function pageOrientation(): string
    {
        return 'P';
    }

    public function margins(): Margins
    {
        return new Margins(0, 0, 0);
    }

    public function cellHeightRatio(): float
    {
        return 1.25;
    }

    public function textColor(): Color
    {
        return Color::gray(0);
    }

    public function fontSize(): float
    {
        return 10.0;
    }

    public function overflow(): Overflow
    {
        return Overflow::None;
    }

    public function creator(): string
    {
        return '';
    }

    public function author(): string
    {
        return '';
    }

    public function debug(): bool
    {
        return false;
    }
}
