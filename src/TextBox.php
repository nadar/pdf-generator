<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Value\Color;

final class TextBox
{
    public function __construct(
        public readonly string $id,
        public readonly float $x,
        public readonly float $y,
        public readonly float $w,
        public readonly ?float $h = null,
        public readonly ?string $font = null,
        public readonly ?float $size = null,
        public readonly string $align = 'L',
        public readonly ?Color $color = null,
        public readonly ?Overflow $overflow = null,
        public readonly float $rotation = 0.0,
        public readonly float $minSize = 6.0,
        public readonly bool $html = false
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (float) ($row['x'] ?? 0),
            (float) ($row['y'] ?? 0),
            (float) ($row['w'] ?? 0),
            isset($row['h']) ? (float) $row['h'] : null,
            isset($row['font']) ? (string) $row['font'] : null,
            isset($row['size']) ? (float) $row['size'] : null,
            isset($row['align']) ? (string) $row['align'] : 'L',
            isset($row['color']) ? Color::hex((string) $row['color']) : null,
            isset($row['overflow']) ? Overflow::fromString((string) $row['overflow']) : null,
            isset($row['rotation']) ? (float) $row['rotation'] : 0.0,
            isset($row['minSize']) ? (float) $row['minSize'] : 6.0,
            isset($row['html']) && (bool) $row['html']
        );
    }

    public function with(
        ?string $id = null,
        ?float $x = null,
        ?float $y = null,
        ?float $w = null,
        ?float $h = null,
        ?string $font = null,
        ?float $size = null,
        ?string $align = null,
        ?Color $color = null,
        ?Overflow $overflow = null,
        ?float $rotation = null,
        ?float $minSize = null,
        ?bool $html = null
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
            $html ?? $this->html
        );
    }

    public function offset(float $dx, float $dy): self
    {
        return $this->with(x: $this->x + $dx, y: $this->y + $dy);
    }
}
