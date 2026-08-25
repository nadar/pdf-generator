<?php

namespace Nadar\PdfGenerator\Font;

use Nadar\PdfGenerator\Exception\FontCacheMissingException;
use Nadar\PdfGenerator\Exception\FontException;
use Nadar\PdfGenerator\Support\FontCompiler;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Registers every face of a {@see FontSet} on a document.
 *
 * Runs once per {@see \Nadar\PdfGenerator\PdfGenerator} instance and fails fast
 * when a compiled definition is missing, because the alternative - TCPDF
 * quietly substituting a core font - is invisible until someone looks at the
 * print.
 */
final class FontRegistry
{
    /** The command that produces the missing definitions. */
    public const BUILD_COMMAND = 'vendor/bin/pdf-generator fonts:build --settings="Your\\Pdf\\Settings"';

    public function __construct(
        private readonly string $fontPath,
        private readonly string $cachePath,
        private readonly FontSet $set
    ) {
    }

    /**
     * @throws FontException            when a configured directory does not exist
     * @throws FontCacheMissingException when a face has no compiled definition
     */
    public function register(Fpdi $pdf): void
    {
        $faces = $this->set->faces();

        // An empty set is legitimate: TCPDF's built-in core fonts need no
        // registration, which keeps throwaway scripts and tests simple.
        if ($faces === []) {
            return;
        }

        $embedded = array_filter($faces, static fn (FontFace $face): bool => !$face->core);

        foreach ($faces as $face) {
            // Core fonts are resolved by TCPDF itself; nothing to compile or embed.
            if ($face->core) {
                $pdf->AddFont($face->tcpdfFamily, $face->tcpdfStyle);
            }
        }

        if ($embedded === []) {
            return;
        }

        if (!is_dir($this->fontPath)) {
            throw new FontException(sprintf(
                'Font path "%s" does not exist. It must hold the font sources listed in fonts().',
                $this->fontPath
            ));
        }

        if (!is_dir($this->cachePath)) {
            throw new FontException(sprintf(
                'Font cache path "%s" does not exist. Create it and run: %s',
                $this->cachePath,
                self::BUILD_COMMAND
            ));
        }

        foreach ($embedded as $face) {
            $cache = FontCompiler::cacheFile($this->cachePath, $face->cacheKey);

            if (!is_file($cache)) {
                throw new FontCacheMissingException($this->missingCacheMessage($face, $cache));
            }

            $pdf->AddFont($face->tcpdfFamily, $face->tcpdfStyle, $cache);
        }
    }

    private function missingCacheMessage(FontFace $face, string $expected): string
    {
        $source = FontCompiler::normalizePath($this->fontPath) . $face->file;
        $sourceState = is_file($source)
            ? sprintf('The source "%s" is present, so it has not been compiled yet.', $source)
            : sprintf('The source "%s" is also missing - check fontPath() and the file name in fonts().', $source);

        return sprintf(
            'No compiled definition for %s (key "%s"). Expected file: "%s". %s Run: %s',
            $face->label(),
            $face->cacheKey,
            $expected,
            $sourceState,
            self::BUILD_COMMAND
        );
    }
}
