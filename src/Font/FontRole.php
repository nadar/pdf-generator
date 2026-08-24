<?php

namespace Nadar\PdfGenerator\Font;

final readonly class FontRole
{
    public function __construct(public string $family, public string $style = '')
    {
    }
}
