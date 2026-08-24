<?php

namespace Nadar\PdfGenerator\Support;

use Nadar\PdfGenerator\Exception\TemplateNotFoundException;
use Nadar\PdfGenerator\Value\PageSize;
use setasign\Fpdi\Tcpdf\Fpdi;

final class TemplateInspector
{
    public static function pageSize(string $file, int $page = 1): PageSize
    {
        self::assertReadable($file);
        $pdf = new Fpdi();
        $pdf->setSourceFile($file);
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);

        return new PageSize((float) $size['width'], (float) $size['height']);
    }

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
