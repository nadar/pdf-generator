<?php

namespace Nadar\PdfGenerator\Font;

/**
 * A named slot in the document's typography, resolved to a TCPDF family/style.
 *
 * Roles are what {@see \Nadar\PdfGenerator\TextBox::$font} refers to, so
 * layouts talk about `headline` or `meta` rather than a font file.
 */
final class FontRole
{
    /**
     * @param string $family the TCPDF family name to select
     * @param string $style  the TCPDF style code (`''`, `B`, `I`, `BI`)
     * @param string $logicalFamily the family as written in the settings
     * @param string $weight canonical weight name
     */
    public function __construct(
        public readonly string $family,
        public readonly string $style = '',
        public readonly string $logicalFamily = '',
        public readonly string $weight = FontWeight::REGULAR
    ) {
    }
}
