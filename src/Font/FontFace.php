<?php

namespace Nadar\PdfGenerator\Font;

final readonly class FontFace
{
    public function __construct(
        public string $family,
        public string $style,
        public string $ttfFile,
        public string $cacheKey
    ) {
    }
}
