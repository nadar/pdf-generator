<?php

namespace Nadar\PdfGenerator;

enum Overflow
{
    case None;
    case Shrink;
    case Clip;
    case Truncate;
    case ShrinkThenClip;

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'none' => self::None,
            'shrink' => self::Shrink,
            'clip' => self::Clip,
            'truncate' => self::Truncate,
            'shrinkthenclip', 'shrink_then_clip', 'shrink-then-clip' => self::ShrinkThenClip,
            default => throw new \InvalidArgumentException(sprintf('Unknown overflow value "%s".', $value)),
        };
    }
}
