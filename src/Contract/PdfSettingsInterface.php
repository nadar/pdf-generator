<?php

namespace Nadar\PdfGenerator\Contract;

use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Margins;

/**
 * Everything a document needs to know before the first page: where the assets
 * live, which fonts exist, and what the defaults are.
 *
 * Implement it by extending {@see \Nadar\PdfGenerator\AbstractPdfSettings},
 * which supplies sensible defaults for everything except the four paths and the
 * font set.
 *
 * A settings class is a plain object, so it is free to use framework helpers
 * (`resource_path()`, container bindings, config values). That does mean it
 * cannot always be instantiated from a bare CLI process - see the
 * `--fonts=`/`--cache=` options of `vendor/bin/pdf-generator`.
 */
interface PdfSettingsInterface
{
    /**
     * Directory holding the font source files (`.ttf`/`.otf`).
     *
     * Only read at build time by `fonts:build`; rendering uses the compiled
     * definitions in {@see fontCachePath()}.
     */
    public function fontPath(): string;

    /**
     * Directory holding the compiled TCPDF font definitions.
     *
     * Must exist before the first render - commit it, or build it in CI. A
     * trailing separator is optional; the package normalises it, which the raw
     * TCPDF API does not.
     */
    public function fontCachePath(): string;

    /** The document's font inventory and the roles layouts refer to. */
    public function fonts(): FontSet;

    /**
     * Directory holding the template PDFs.
     *
     * {@see \Nadar\PdfGenerator\PdfGenerator::page()} and `stamp()` take a
     * basename relative to this directory, not a full path.
     */
    public function templatePath(): string;

    /**
     * Default page format: a named size like `A4`, or `[width, height]` in mm.
     *
     * Ignored for pages that stamp a template, which derive their size from it.
     *
     * @return string|list<float>
     */
    public function pageFormat(): string|array;

    /** Default page orientation, `P` or `L`. */
    public function pageOrientation(): string;

    /**
     * Page margins in mm.
     *
     * Template overlays normally want all zeroes, so that measured design
     * coordinates are page coordinates.
     */
    public function margins(): Margins;

    /**
     * Line height as a multiple of the font size. TCPDF's default is 1.25.
     *
     * This participates in vertical text placement: it sets the height of the
     * cell a line sits in, and therefore where the baseline lands. Changing it
     * moves every baseline in the document.
     */
    public function cellHeightRatio(): float;

    /** Default text colour, used by boxes that declare none. */
    public function textColor(): Color;

    /** Default font size in pt, used by boxes that declare none. */
    public function fontSize(): float;

    /**
     * Default overflow policy for boxes that declare none.
     *
     * {@see Overflow::ShrinkThenClip} is the pragmatic choice for data-driven
     * documents; the built-in default is {@see Overflow::None}.
     */
    public function overflow(): Overflow;

    /** PDF `Creator` metadata; empty string leaves TCPDF's default in place. */
    public function creator(): string;

    /** PDF `Author` metadata; empty string leaves TCPDF's default in place. */
    public function author(): string;

    /**
     * Draw box outlines, baselines and resolved metrics over the output.
     *
     * A development aid; never enable it for production output.
     */
    public function debug(): bool;

    /**
     * Factory for the underlying FPDI document, or `null` for the default.
     *
     * The seam for a TCPDF subclass with `Header()`/`Footer()` overrides or
     * custom error handling.
     */
    public function pdfFactory(): ?PdfFactoryInterface;
}
