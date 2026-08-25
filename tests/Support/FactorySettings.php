<?php

namespace Nadar\PdfGenerator\Tests\Support;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Contract\PdfFactoryInterface;
use Nadar\PdfGenerator\Font\FontSet;

/** Settings that route document creation through a custom factory. */
final class FactorySettings extends AbstractPdfSettings
{
    public function __construct(
        private readonly string $workspace,
        private readonly PdfFactoryInterface $factory
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
        return new FontSet();
    }

    public function pdfFactory(): PdfFactoryInterface
    {
        return $this->factory;
    }
}
