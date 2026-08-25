<?php

declare(strict_types=1);

namespace Nadar\PdfGenerator\Examples;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\Support\FontCompiler;

/**
 * Minimal settings shared by the examples.
 *
 * Real projects declare their brand faces unconditionally and build the cache
 * in CI. The examples fall back to TCPDF's core fonts when nothing has been
 * compiled, so they run straight after `composer install`.
 *
 * To use real fonts, put Inter-Regular.ttf and Inter-Bold.ttf into
 * examples/assets/fonts and run:
 *
 *   vendor/bin/pdf-generator fonts:build \
 *       --fonts=examples/assets/fonts --cache=examples/assets/fonts/cache
 */
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
        return __DIR__ . '/output';
    }

    public function fonts(): FontSet
    {
        if (!$this->brandFontsAvailable()) {
            return FontSet::make()
                ->coreFamily('helvetica')
                ->role('regular', 'helvetica')
                ->role('headline', 'helvetica', 'bold');
        }

        return FontSet::make()
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
            ->role('regular', 'inter')
            ->role('headline', 'inter', 'bold');
    }

    /** The safe default for content that comes out of a database. */
    public function overflow(): Overflow
    {
        return Overflow::ShrinkThenClip;
    }

    public function brandFontsAvailable(): bool
    {
        foreach (['inter', 'interb'] as $key) {
            if (!is_file(FontCompiler::cacheFile($this->fontCachePath(), $key))) {
                return false;
            }
        }

        return true;
    }
}
