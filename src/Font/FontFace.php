<?php

namespace Nadar\PdfGenerator\Font;

final class FontFace
{
    public function __construct(
        public readonly string $family,
        public readonly string $style,
        public readonly string $ttfFile,
        public readonly string $cacheKey
    ) {
    }
}
