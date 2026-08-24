<?php

namespace Nadar\PdfGenerator\Font;

final class FontRole
{
    public function __construct(public readonly string $family, public readonly string $style = '')
    {
    }
}
