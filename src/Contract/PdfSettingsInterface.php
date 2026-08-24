<?php

namespace Nadar\PdfGenerator\Contract;

use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Margins;

interface PdfSettingsInterface
{
    public function fontPath(): string;

    public function fontCachePath(): string;

    public function fonts(): FontSet;

    public function templatePath(): string;

    public function pageFormat(): string|array;

    public function pageOrientation(): string;

    public function margins(): Margins;

    public function cellHeightRatio(): float;

    public function textColor(): Color;

    public function fontSize(): float;

    public function overflow(): Overflow;

    public function creator(): string;

    public function author(): string;

    public function debug(): bool;
}
