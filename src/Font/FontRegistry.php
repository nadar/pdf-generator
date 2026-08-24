<?php

namespace Nadar\PdfGenerator\Font;

use Nadar\PdfGenerator\Exception\FontCacheMissingException;
use Nadar\PdfGenerator\Exception\FontException;
use setasign\Fpdi\Tcpdf\Fpdi;

final readonly class FontRegistry
{
    public function __construct(private string $fontPath, private string $cachePath, private FontSet $set)
    {
    }

    public function register(Fpdi $pdf): void
    {
        if (!is_dir($this->fontPath)) {
            throw new FontException(sprintf('Font path "%s" does not exist.', $this->fontPath));
        }

        if (!is_dir($this->cachePath)) {
            throw new FontException(sprintf('Font cache path "%s" does not exist.', $this->cachePath));
        }

        foreach ($this->set->faces() as $face) {
            $cache = rtrim($this->cachePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $face->cacheKey . '.php';
            if (!is_file($cache)) {
                throw new FontCacheMissingException(sprintf(
                    'Font cache for "%s" not found in %s -- run: vendor/bin/pdf-generator fonts:build --settings="Your\\Pdf\\Settings"',
                    $face->cacheKey,
                    $this->cachePath
                ));
            }

            $pdf->AddFont($face->family, $face->style, $cache);
        }
    }
}
