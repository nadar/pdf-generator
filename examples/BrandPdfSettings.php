<?php

declare(strict_types=1);

namespace Nadar\PdfGenerator\Examples;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;

final class BrandPdfSettings extends AbstractPdfSettings
{
    public function fontPath(): string
    {
        return __DIR__ . '/assets/fonts';
    }

    public function fontCachePath(): string
    {
        return __DIR__ . '/assets/fonts/cache';
    }

    public function templatePath(): string
    {
        return __DIR__ . '/assets/templates';
    }

    public function fonts(): FontSet
    {
        return FontSet::make()
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf', 'Inter-Italic.ttf', 'Inter-BoldItalic.ttf')
            ->role('regular', 'inter')
            ->role('headline', 'inter', 'B');
    }
}
