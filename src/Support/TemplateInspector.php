<?php

namespace Nadar\PdfGenerator\Support;

use Nadar\PdfGenerator\Exception\TemplateNotFoundException;
use Nadar\PdfGenerator\Value\PageSize;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Reads geometry out of a template PDF without starting a document.
 *
 * Used by {@see \Nadar\PdfGenerator\PdfGenerator::page()} to derive a page's
 * size from the template it stamps.
 */
final class TemplateInspector
{
    /**
     * Page size in mm, taken from the template's CropBox.
     *
     * @throws TemplateNotFoundException when the file is missing or unreadable
     */
    public static function pageSize(string $file, int $page = 1): PageSize
    {
        self::assertReadable($file);
        $pdf = new Fpdi();
        $pdf->setSourceFile($file);
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);
        if (!is_array($size)) {
            throw new \RuntimeException(sprintf('Unable to read template size from "%s".', $file));
        }

        return new PageSize((float) $size['width'], (float) $size['height']);
    }

    /**
     * How many pages the template has.
     *
     * @throws TemplateNotFoundException when the file is missing or unreadable
     */
    public static function pageCount(string $file): int
    {
        self::assertReadable($file);
        $pdf = new Fpdi();

        return (int) $pdf->setSourceFile($file);
    }

    private static function assertReadable(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new TemplateNotFoundException(sprintf('Template "%s" was not found or is not readable.', $file));
        }
    }
}
