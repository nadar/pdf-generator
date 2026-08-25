<?php

namespace Nadar\PdfGenerator\Font;

use Nadar\PdfGenerator\Exception\FontException;
use Nadar\PdfGenerator\Exception\MissingFontStyleException;
use Nadar\PdfGenerator\Support\FontCompiler;

/**
 * The document's font inventory: which files exist, and which roles use them.
 *
 * Two registration styles, which mix freely:
 *
 * ```php
 * FontSet::make()
 *     // the four faces TCPDF models as styles of one family
 *     ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
 *     // any additional weight a brand kit ships
 *     ->face('brother', 'regular', 'Brother-1816-Regular.ttf')
 *     ->face('brother', 'medium', 'Brother-1816-Medium.ttf')
 *     ->face('brother', 'bold', 'Brother-1816-Bold.ttf')
 *     ->role('body', 'inter')
 *     ->role('headline', 'brother', 'medium')
 *     ->role('title', 'brother', 'bold');
 * ```
 *
 * A role referring to a weight with no registered file throws immediately, at
 * configuration time - synthetic bold/italic is a silent no-op for embedded
 * subset fonts, so guessing is never the right fallback.
 */
final class FontSet
{
    /**
     * TCPDF's built-in fonts and the weights each provides.
     *
     * @var array<string,list<string>>
     */
    private const CORE_FONTS = [
        'courier' => [FontWeight::REGULAR, FontWeight::BOLD, FontWeight::ITALIC, FontWeight::BOLD_ITALIC],
        'helvetica' => [FontWeight::REGULAR, FontWeight::BOLD, FontWeight::ITALIC, FontWeight::BOLD_ITALIC],
        'times' => [FontWeight::REGULAR, FontWeight::BOLD, FontWeight::ITALIC, FontWeight::BOLD_ITALIC],
        'symbol' => [FontWeight::REGULAR],
        'zapfdingbats' => [FontWeight::REGULAR],
    ];

    /** @var array<string,array<string,FontFace>> logical family => canonical weight => face */
    private array $families = [];

    /** @var array<string,FontFace> "tcpdfFamily|tcpdfStyle" => face */
    private array $byTcpdf = [];

    /** @var array<string,FontRole> */
    private array $roles = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Register the four faces TCPDF treats as styles of a single family.
     *
     * Shorthand for four {@see face()} calls. Pass `null` for a style the brand
     * does not ship; a role asking for it then fails loudly instead of
     * rendering a fake.
     */
    public function family(string $name, string $regular, ?string $bold = null, ?string $italic = null, ?string $boldItalic = null): self
    {
        $this->face($name, FontWeight::REGULAR, $regular);

        foreach ([FontWeight::BOLD => $bold, FontWeight::ITALIC => $italic, FontWeight::BOLD_ITALIC => $boldItalic] as $weight => $file) {
            if ($file !== null && $file !== '') {
                $this->face($name, $weight, $file);
            }
        }

        return $this;
    }

    /**
     * Register one font file as a named weight of a family.
     *
     * `$weight` accepts canonical names (`regular`, `bold`, `italic`,
     * `bolditalic`), TCPDF style codes (`''`, `B`, `I`, `BI`) and any custom
     * label a brand kit uses (`medium`, `light`, `semibold`, `extrabold`, ...).
     * Custom weights get their own TCPDF family behind the scenes; see
     * {@see FontWeight}.
     *
     * @param string $file basename of the font source inside `fontPath()`
     */
    public function face(string $family, string $weight, string $file): self
    {
        $weight = FontWeight::normalize($weight);
        [$tcpdfFamily, $tcpdfStyle] = FontWeight::toTcpdf($family, $weight);

        $face = new FontFace($family, $weight, $file, FontCompiler::keyFor($file), $tcpdfFamily, $tcpdfStyle);

        $this->families[$family][$weight] = $face;
        $this->byTcpdf[$tcpdfFamily . '|' . $tcpdfStyle] = $face;

        return $this;
    }

    /**
     * Register one of TCPDF's built-in fonts as a family.
     *
     * Core fonts need no compilation and are not embedded, which makes them the
     * right choice for throwaway scripts, tests and examples - and the only way
     * to get a real bold face without a font binary. They are metrically
     * standard rather than brand-accurate, so production print work should use
     * {@see family()} or {@see face()} with real files.
     *
     * @param string $family one of courier, helvetica, times (which have all four
     *                       styles), or symbol / zapfdingbats (regular only)
     *
     * @throws \Nadar\PdfGenerator\Exception\FontException on an unknown core font
     */
    public function coreFamily(string $family = 'helvetica'): self
    {
        $family = strtolower($family);
        $weights = self::CORE_FONTS[$family] ?? null;

        if ($weights === null) {
            throw new FontException(sprintf(
                'Unknown core font "%s". TCPDF ships: %s.',
                $family,
                implode(', ', array_keys(self::CORE_FONTS))
            ));
        }

        foreach ($weights as $weight) {
            [$tcpdfFamily, $tcpdfStyle] = FontWeight::toTcpdf($family, $weight);
            $face = new FontFace($family, $weight, '', $family . strtolower($tcpdfStyle), $tcpdfFamily, $tcpdfStyle, true);

            $this->families[$family][$weight] = $face;
            $this->byTcpdf[$tcpdfFamily . '|' . $tcpdfStyle] = $face;
        }

        return $this;
    }

    /**
     * Name a role that layouts refer to through {@see \Nadar\PdfGenerator\TextBox::$font}.
     *
     * @param string $weight canonical weight, TCPDF style code, or custom weight label
     *
     * @throws MissingFontStyleException when no face is registered for that family/weight
     */
    public function role(string $name, string $family, string $weight = FontWeight::REGULAR): self
    {
        $weight = FontWeight::normalize($weight);
        $face = $this->families[$family][$weight] ?? null;

        if ($face === null) {
            throw new MissingFontStyleException($this->missingFaceMessage($name, $family, $weight));
        }

        $this->roles[$name] = new FontRole($face->tcpdfFamily, $face->tcpdfStyle, $family, $weight);

        return $this;
    }

    /**
     * Every registered face, keyed by its TCPDF family/style pair.
     *
     * Used by {@see FontRegistry} to register the faces on a document, and by
     * `fonts:check` to verify the cache.
     *
     * @return array<string,FontFace>
     */
    public function faces(): array
    {
        return $this->byTcpdf;
    }

    /**
     * Resolve a role name, falling back to something usable.
     *
     * `null` means "the default role": role `regular` if defined, otherwise the
     * regular face of the first registered family, otherwise TCPDF's built-in
     * Helvetica - which keeps an empty `FontSet` renderable for quick scripts
     * and tests.
     */
    public function roleOrDefault(?string $name): FontRole
    {
        $role = $name ?? FontWeight::REGULAR;

        if (isset($this->roles[$role])) {
            return $this->roles[$role];
        }

        $family = array_key_first($this->families);
        if ($family === null) {
            return new FontRole('helvetica', '', 'helvetica');
        }

        $face = $this->families[$family][FontWeight::REGULAR] ?? null;
        if ($face === null) {
            $first = reset($this->families[$family]);
            if ($first === false) {
                return new FontRole('helvetica', '', 'helvetica');
            }
            $face = $first;
        }

        return new FontRole($face->tcpdfFamily, $face->tcpdfStyle, $face->family, $face->weight);
    }

    /** @return list<string> logical family names, in registration order */
    public function familyNames(): array
    {
        return array_keys($this->families);
    }

    /** @return list<string> canonical weights registered for a logical family */
    public function weights(string $family): array
    {
        return array_keys($this->families[$family] ?? []);
    }

    /**
     * Whether a *TCPDF* family has a face for this TCPDF style code.
     *
     * Takes the TCPDF family (as found on a {@see FontRole}), not the logical
     * one, because this backs the HTML `<b>`/`<i>` guard.
     */
    public function supportsStyle(string $tcpdfFamily, string $style): bool
    {
        return isset($this->byTcpdf[$tcpdfFamily . '|' . strtoupper($style)]);
    }

    /** The TCPDF font key for a TCPDF family/style pair, or `null` when unregistered. */
    public function cacheKey(string $tcpdfFamily, string $style): ?string
    {
        return ($this->byTcpdf[$tcpdfFamily . '|' . strtoupper($style)] ?? null)?->cacheKey;
    }

    private function missingFaceMessage(string $role, string $family, string $weight): string
    {
        if (!isset($this->families[$family])) {
            $known = $this->familyNames();

            return sprintf(
                'Role "%s" refers to unknown font family "%s". Registered families: %s. '
                . 'Add it with ->family(\'%s\', \'...\') or ->face(\'%s\', \'%s\', \'...\').',
                $role,
                $family,
                $known === [] ? '(none)' : implode(', ', $known),
                $family,
                $family,
                $weight
            );
        }

        $suggestion = sprintf('%s-%s.ttf', ucfirst($family), FontWeight::label($weight));

        return sprintf(
            'Role "%s" requires weight "%s" of family "%s", but no face is registered for it. '
            . 'Registered weights: %s. Synthetic bold/italic is a no-op for embedded subset '
            . 'fonts, so add the real file: ->face(\'%s\', \'%s\', \'%s\') (-> key "%s").',
            $role,
            $weight,
            $family,
            implode(', ', $this->weights($family)),
            $family,
            $weight,
            $suggestion,
            FontCompiler::keyFor($suggestion)
        );
    }
}
