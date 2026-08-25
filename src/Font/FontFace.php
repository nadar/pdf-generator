<?php

namespace Nadar\PdfGenerator\Font;

/**
 * One concrete font file, resolved to the TCPDF family/style it registers under.
 */
final class FontFace
{
    /**
     * @param string $family       the logical family from the settings, e.g. `brother`
     * @param string $weight       canonical weight name, e.g. `medium`
     * @param string $file         source basename, e.g. `Brother-1816-Medium.ttf`
     * @param string $cacheKey     TCPDF font key / definition basename, e.g. `brother1816medium`
     * @param string $tcpdfFamily  family TCPDF is asked for, e.g. `brothermedium`
     * @param string $tcpdfStyle   `''`, `B`, `I` or `BI`
     */
    public function __construct(
        public readonly string $family,
        public readonly string $weight,
        public readonly string $file,
        public readonly string $cacheKey,
        public readonly string $tcpdfFamily,
        public readonly string $tcpdfStyle
    ) {
    }

    /** Backwards-compatible alias for {@see $file}. */
    public function ttfFile(): string
    {
        return $this->file;
    }

    /** `family/weight`, for error messages and CLI output. */
    public function label(): string
    {
        return $this->family . '/' . $this->weight;
    }
}
