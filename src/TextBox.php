<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Exception\InvalidValueException;
use Nadar\PdfGenerator\Support\Cast;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\Rect;

/**
 * One immutable text slot: where it sits, how it looks, what happens when the
 * content is too long.
 *
 * All coordinates are millimetres measured from the **top-left of the page**.
 *
 * `y` means what {@see $anchor} says it means. By default it is the top edge of
 * the first line's *cell*, which is TCPDF's native reference point and depends
 * on the font's ascent/descent and on `cellHeightRatio`. Design tools hand over
 * baselines instead, so pass `anchor: Anchor::Baseline` to use a measured
 * baseline verbatim rather than correcting it by hand.
 *
 * ```php
 * new TextBox(
 *     id: 'title',
 *     x: 53.2, y: 58.5, w: 120.0, h: 11.5,
 *     font: 'bold', size: 24.0,
 *     overflow: Overflow::ShrinkThenClip,
 * );
 * ```
 *
 * @see PdfGenerator::write()
 * @see PdfGenerator::probe() to read the resolved geometry back
 *
 * @phpstan-type TextBoxArray array{
 *     id?: string,
 *     x?: float|int|string,
 *     y?: float|int|string,
 *     w?: float|int|string,
 *     h?: float|int|string,
 *     font?: string,
 *     size?: float|int|string,
 *     align?: string,
 *     color?: string,
 *     overflow?: string,
 *     rotation?: float|int|string,
 *     minSize?: float|int|string,
 *     html?: bool|int|string,
 *     anchor?: string,
 *     maxLines?: float|int|string,
 * }
 */
final class TextBox implements Slot
{
    /**
     * Fraction of the requested size used as the shrink floor when
     * {@see $minSize} is left unset.
     *
     * A relative floor fails loudly on genuinely unfittable content instead of
     * silently rendering it at a size nobody can read in print.
     */
    public const DEFAULT_MIN_SIZE_RATIO = 0.6;

    public readonly Align $align;

    /**
     * @param string       $id       slot name; also the key {@see PdfGenerator::writeAll()}
     *                               reads from the data array, and the label drawn in debug mode
     * @param float        $x        left edge in mm
     * @param float        $y        vertical position in mm, interpreted per $anchor
     * @param float        $w        box width in mm; text wraps at this width
     * @param null|float   $h        box height in mm. Only meaningful together with an
     *                               {@see Overflow} policy - without one, nothing constrains
     *                               the text to it. A policy needs either this or
     *                               {@see $maxLines} to act on.
     * @param null|string  $font     role name from the settings' {@see \Nadar\PdfGenerator\Font\FontSet};
     *                               `null` uses the default role
     * @param null|float   $size     font size in pt; `null` uses the settings' default
     * @param Align|string $align    horizontal alignment; plain `'L'|'C'|'R'|'J'` still accepted
     * @param null|Color   $color    text colour; `null` uses the settings' default
     * @param null|Overflow $overflow what to do when the text exceeds $h; `null` uses the
     *                               settings' default
     * @param float        $rotation clockwise rotation in degrees around ($x, $y)
     * @param null|float   $minSize  shrink floor in pt; `null` means
     *                               {@see DEFAULT_MIN_SIZE_RATIO} of the requested size
     * @param bool         $html     render $text as (a small subset of) HTML rather than plain text
     * @param Anchor       $anchor   what $y refers to
     * @param null|int     $maxLines cap the line count when shrinking. `maxLines: 1` is the
     *                               "headline must stay on one line, shrink until it does"
     *                               case that a height alone cannot express. Surplus words are
     *                               dropped only once the shrink floor is reached - except on
     *                               an `html` box, which shrinks and then clips, since cutting
     *                               markup at a character offset would break a tag.
     */
    public function __construct(
        public readonly string $id,
        public readonly float $x,
        public readonly float $y,
        public readonly float $w,
        public readonly ?float $h = null,
        public readonly ?string $font = null,
        public readonly ?float $size = null,
        Align|string $align = Align::Left,
        public readonly ?Color $color = null,
        public readonly ?Overflow $overflow = null,
        public readonly float $rotation = 0.0,
        public readonly ?float $minSize = null,
        public readonly bool $html = false,
        public readonly Anchor $anchor = Anchor::Top,
        public readonly ?int $maxLines = null
    ) {
        $this->align = Align::coerce($align);

        if ($maxLines !== null && $maxLines < 1) {
            throw new InvalidValueException(sprintf('maxLines for box "%s" must be at least 1, %d given.', $id, $maxLines));
        }
    }

    /**
     * Build a box from a plain array, e.g. one decoded from JSON or YAML.
     *
     * Recognised keys: `id`, `x`, `y`, `w`, `h`, `font`, `size`, `align`,
     * `color` (hex), `overflow`, `rotation`, `minSize`, `html`, `anchor`,
     * `maxLines`.
     *
     * The accepted shape is published as the `TextBoxArray` type alias: a project
     * keeping its layouts in constants or config can import it with
     * `@phpstan-import-type TextBoxArray from \Nadar\PdfGenerator\TextBox` and have
     * them statically checked.
     *
     * @param array<string,mixed> $row
     *
     * @throws \Nadar\PdfGenerator\Exception\ConfigurationException on a non-castable value
     */
    public static function fromArray(array $row): self
    {
        return new self(
            Cast::toString($row['id'] ?? '', 'id'),
            Cast::toFloat($row['x'] ?? 0, 'x'),
            Cast::toFloat($row['y'] ?? 0, 'y'),
            Cast::toFloat($row['w'] ?? 0, 'w'),
            isset($row['h']) ? Cast::toFloat($row['h'], 'h') : null,
            isset($row['font']) ? Cast::toString($row['font'], 'font') : null,
            isset($row['size']) ? Cast::toFloat($row['size'], 'size') : null,
            isset($row['align']) ? Align::coerce(Cast::toString($row['align'], 'align')) : Align::Left,
            isset($row['color']) ? Color::hex(Cast::toString($row['color'], 'color')) : null,
            isset($row['overflow']) ? Overflow::fromString(Cast::toString($row['overflow'], 'overflow')) : null,
            isset($row['rotation']) ? Cast::toFloat($row['rotation'], 'rotation') : 0.0,
            isset($row['minSize']) ? Cast::toFloat($row['minSize'], 'minSize') : null,
            isset($row['html']) && Cast::toBool($row['html'], 'html'),
            isset($row['anchor']) ? self::anchorFromString(Cast::toString($row['anchor'], 'anchor')) : Anchor::Top,
            isset($row['maxLines']) ? (int) Cast::toFloat($row['maxLines'], 'maxLines') : null
        );
    }

    /**
     * Copy with selected properties replaced.
     *
     * `null` means "keep the current value", so this cannot be used to unset an
     * optional property - build a new box for that.
     */
    public function with(
        ?string $id = null,
        ?float $x = null,
        ?float $y = null,
        ?float $w = null,
        ?float $h = null,
        ?string $font = null,
        ?float $size = null,
        Align|string|null $align = null,
        ?Color $color = null,
        ?Overflow $overflow = null,
        ?float $rotation = null,
        ?float $minSize = null,
        ?bool $html = null,
        ?Anchor $anchor = null,
        ?int $maxLines = null
    ): self {
        return new self(
            $id ?? $this->id,
            $x ?? $this->x,
            $y ?? $this->y,
            $w ?? $this->w,
            $h ?? $this->h,
            $font ?? $this->font,
            $size ?? $this->size,
            $align ?? $this->align,
            $color ?? $this->color,
            $overflow ?? $this->overflow,
            $rotation ?? $this->rotation,
            $minSize ?? $this->minSize,
            $html ?? $this->html,
            $anchor ?? $this->anchor,
            $maxLines ?? $this->maxLines
        );
    }

    /**
     * Copy shifted by ($dx, $dy) mm.
     *
     * The building block for repeated row slots; see {@see Layout::repeat()}
     * for the declarative version.
     */
    public function offset(float $dx, float $dy): self
    {
        return $this->with(x: $this->x + $dx, y: $this->y + $dy);
    }

    /**
     * The declared box.
     *
     * Height is zero when the box declares none - the rendered extent then
     * depends on the text, which {@see PdfGenerator::probe()} reports.
     */
    public function bounds(): Rect
    {
        return new Rect($this->x, $this->y, $this->w, $this->h ?? 0.0);
    }

    /** Copy moved to an absolute position. */
    public function at(float $x, float $y): self
    {
        return $this->with(x: $x, y: $y);
    }

    /**
     * The shrink floor in pt for a given effective size.
     *
     * Returns {@see $minSize} when set, otherwise
     * {@see DEFAULT_MIN_SIZE_RATIO} of $size.
     */
    public function minSizeFor(float $size): float
    {
        return $this->minSize ?? $size * self::DEFAULT_MIN_SIZE_RATIO;
    }

    /** @throws InvalidValueException on an unknown name */
    private static function anchorFromString(string $value): Anchor
    {
        return match (strtolower(str_replace(['-', '_'], '', $value))) {
            '', 'top', 'celltop' => Anchor::Top,
            'baseline' => Anchor::Baseline,
            'capheight', 'cap', 'inktop' => Anchor::CapHeight,
            default => throw new InvalidValueException(sprintf(
                'Unknown anchor "%s". Use "top", "baseline" or "capHeight".',
                $value
            )),
        };
    }
}
