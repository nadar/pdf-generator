<?php

namespace Nadar\PdfGenerator\Tests\Support;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;

/**
 * Settings for the integration suite.
 *
 * The font set is empty by default, so rendering falls back to TCPDF's built-in
 * core fonts and the suite needs no committed binary font assets.
 */
final class TestSettings extends AbstractPdfSettings
{
    public function __construct(
        private readonly string $workspace,
        private readonly FontSet $fonts = new FontSet(),
        private readonly Overflow $overflow = Overflow::None,
        private readonly float $ratio = 1.25,
        private readonly bool $debugEnabled = false
    ) {
    }

    public function fontPath(): string
    {
        return $this->workspace;
    }

    public function fontCachePath(): string
    {
        return $this->workspace;
    }

    public function templatePath(): string
    {
        return $this->workspace;
    }

    public function fonts(): FontSet
    {
        return $this->fonts;
    }

    public function fontSize(): float
    {
        return 12.0;
    }

    public function cellHeightRatio(): float
    {
        return $this->ratio;
    }

    public function overflow(): Overflow
    {
        return $this->overflow;
    }

    public function debug(): bool
    {
        return $this->debugEnabled;
    }
}
