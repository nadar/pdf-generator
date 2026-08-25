<?php

namespace Nadar\PdfGenerator\Support;

/**
 * A resolved image: its pixel dimensions plus how to hand it to TCPDF.
 */
final class ImageSource
{
    /**
     * @param string      $source the original path or URL
     * @param int         $width  pixel width
     * @param int         $height pixel height
     * @param null|string $data   raw bytes for a fetched remote image; `null` for a
     *                            local file, which TCPDF reads from disk itself
     */
    public function __construct(
        public readonly string $source,
        public readonly int $width,
        public readonly int $height,
        public readonly ?string $data = null
    ) {
    }

    /** Width divided by height, guarded against a zero height. */
    public function ratio(): float
    {
        return $this->width / max(1, $this->height);
    }

    /**
     * The value to pass as TCPDF's `$file` argument.
     *
     * TCPDF reads an image from a string when the argument starts with `@`,
     * which is how a remote image is embedded without a second download.
     */
    public function reference(): string
    {
        return $this->data === null ? $this->source : '@' . $this->data;
    }
}
