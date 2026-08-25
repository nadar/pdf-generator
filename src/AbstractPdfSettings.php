<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Contract\PdfFactoryInterface;
use Nadar\PdfGenerator\Contract\PdfSettingsInterface;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Margins;

/**
 * Defaults for everything in {@see PdfSettingsInterface} except the paths and
 * the font set.
 *
 * Extend this and override only what differs:
 *
 * ```php
 * final class BrandPdfSettings extends AbstractPdfSettings
 * {
 *     public function fontPath(): string      { return resource_path('pdf/fonts'); }
 *     public function fontCachePath(): string { return resource_path('pdf/fonts/cache'); }
 *     public function templatePath(): string  { return resource_path('pdf/templates'); }
 *
 *     public function fonts(): FontSet
 *     {
 *         return FontSet::make()
 *             ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
 *             ->role('regular', 'inter')
 *             ->role('bold', 'inter', 'bold');
 *     }
 * }
 * ```
 */
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

    /** Zero margins, so measured design coordinates are page coordinates. */
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
        return Color::black();
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

    public function pdfFactory(): ?PdfFactoryInterface
    {
        return null;
    }
}
