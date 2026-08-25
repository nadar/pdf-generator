<?php

namespace Nadar\PdfGenerator;

/**
 * What to do when text does not fit the height declared on a {@see TextBox}.
 *
 * A policy only takes effect when the box has an `h`; without one there is
 * nothing to overflow. Note that shrinking measures *height*, so text wide
 * enough to wrap will wrap first and shrink second - use
 * {@see TextBox::$maxLines} when the design demands a single line.
 */
enum Overflow
{
    /** Draw as-is and let long text spill out of the box. */
    case None;

    /**
     * Step the size down until the text fits, and **throw** when even
     * {@see TextBox::minSizeFor()} is too large.
     *
     * Good for content you control, where overflowing is a bug worth failing
     * on. Iterating CMS or user data, prefer {@see ShrinkThenClip}: one
     * unusually long title should not turn into a 500.
     *
     * @throws \Nadar\PdfGenerator\Exception\OverflowException when it cannot fit
     */
    case Shrink;

    /** Draw at the requested size, clipped to the box. Anything past the edge is cut off. */
    case Clip;

    /** Cut the text at a word boundary and append an ellipsis so it fits on one line. */
    case Truncate;

    /**
     * Shrink as far as the floor allows, then clip whatever still does not fit.
     *
     * The safe choice for data-driven slots: always renders something, never
     * throws, never pushes the layout apart.
     */
    case ShrinkThenClip;

    /**
     * Parse a policy name, as used in array-defined layouts.
     *
     * Accepts `none`, `shrink`, `clip`, `truncate` and `shrinkThenClip` in
     * camel, snake or kebab case.
     *
     * @throws \InvalidArgumentException on an unknown name
     */
    public static function fromString(string $value): self
    {
        return match (strtolower(str_replace(['-', '_'], '', trim($value)))) {
            'none' => self::None,
            'shrink' => self::Shrink,
            'clip' => self::Clip,
            'truncate' => self::Truncate,
            'shrinkthenclip' => self::ShrinkThenClip,
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown overflow value "%s". Expected one of: none, shrink, clip, truncate, shrinkThenClip.',
                $value
            )),
        };
    }

    /** Whether this policy resizes the text. */
    public function shrinks(): bool
    {
        return $this === self::Shrink || $this === self::ShrinkThenClip;
    }
}
