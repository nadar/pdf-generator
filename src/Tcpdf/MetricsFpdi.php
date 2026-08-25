<?php

namespace Nadar\PdfGenerator\Tcpdf;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * The document class {@see \Nadar\PdfGenerator\PdfGenerator} instantiates by default.
 *
 * TCPDF exposes ascent and descent publicly but keeps the rest of the font
 * descriptor internal, so cap height - the "top of the capitals" a designer
 * measures off a reference PDF - is unreachable from outside. This subclass
 * surfaces it.
 *
 * A custom {@see \Nadar\PdfGenerator\Contract\PdfFactoryInterface} that wants
 * `Anchor::CapHeight` (or its own `Header()`/`Footer()`) should extend this
 * class rather than `Fpdi` directly.
 */
class MetricsFpdi extends Fpdi
{
    /**
     * Cap height in 1/1000 em assumed when a font carries no `CapHeight` entry.
     *
     * Matches TCPDF's own fallback for such fonts.
     */
    public const FALLBACK_CAP_HEIGHT = 700;

    /**
     * Pin the document id that TCPDF otherwise seeds randomly.
     *
     * TCPDF derives `file_id` from a random seed and writes it into the XMP
     * metadata as `xmpMM:DocumentID`/`InstanceID`, so two renders of identical
     * input still differ byte for byte. There is no public setter, which is why
     * this lives here.
     *
     * @param string $seed hashed into the id; the same seed always yields the same id
     */
    public function pinFileId(string $seed): void
    {
        $this->file_id = md5($seed);
    }

    /**
     * The PDF font descriptor for a family/style, in 1/1000 em units.
     *
     * Selects the font without emitting a font-selection operator, then restores
     * the previous selection, so inspecting metrics does not alter the output.
     *
     * @return array<string,mixed> empty when the font reports no descriptor
     */
    public function fontDescriptor(string $family, string $style = ''): array
    {
        $previousFamily = $this->FontFamily;
        $previousStyle = $this->FontStyle;

        try {
            $this->SetFont($family, $style, null, '', 'default', false);

            $current = $this->CurrentFont;
            if (!is_array($current) || !isset($current['desc']) || !is_array($current['desc'])) {
                return [];
            }

            /** @var array<string,mixed> $descriptor */
            $descriptor = $current['desc'];

            return $descriptor;
        } finally {
            if (is_string($previousFamily) && $previousFamily !== '') {
                $this->SetFont($previousFamily, is_string($previousStyle) ? $previousStyle : '', null, '', 'default', false);
            }
        }
    }

    /**
     * Cap height for a family/style at a given size, in user units (mm).
     *
     * Falls back to {@see FALLBACK_CAP_HEIGHT} for fonts whose descriptor omits
     * the value.
     *
     * @param float $sizePt font size in points
     */
    public function fontCapHeight(string $family, string $style, float $sizePt): float
    {
        $cap = $this->fontDescriptor($family, $style)['CapHeight'] ?? null;

        $units = is_numeric($cap) && (float) $cap > 0
            ? (float) $cap
            : (float) self::FALLBACK_CAP_HEIGHT;

        return ($units * $sizePt / 1000) / $this->k;
    }
}
