<?php

namespace Nadar\PdfGenerator\Font;

use Nadar\PdfGenerator\Exception\MissingFontStyleException;
use Nadar\PdfGenerator\Support\FontCompiler;

final class FontSet
{
    /** @var array<string,array{regular:string,bold:?string,italic:?string,boldItalic:?string}> */
    private array $families = [];

    /** @var array<string,FontRole> */
    private array $roles = [];

    public static function make(): self
    {
        return new self();
    }

    public function family(string $name, string $regular, ?string $bold = null, ?string $italic = null, ?string $boldItalic = null): self
    {
        $this->families[$name] = [
            'regular' => $regular,
            'bold' => $bold,
            'italic' => $italic,
            'boldItalic' => $boldItalic,
        ];

        return $this;
    }

    public function role(string $name, string $family, string $style = ''): self
    {
        $style = strtoupper($style);
        $face = $this->faceFile($family, $style);
        if ($face === null) {
            $expected = FontCompiler::keyFor(sprintf('%s-%s.ttf', ucfirst($family), self::styleLabel($style)));
            throw new MissingFontStyleException(sprintf(
                'Role "%s" requires style "%s" on family "%s", but no matching face is configured. Synthetic bold/italic is a no-op for embedded subset fonts. Add %s-%s.ttf (-> key "%s").',
                $name,
                $style,
                $family,
                ucfirst($family),
                self::styleLabel($style),
                $expected
            ));
        }

        $this->roles[$name] = new FontRole($family, $style);

        return $this;
    }

    /** @return array<string,FontFace> */
    public function faces(): array
    {
        $faces = [];
        foreach ($this->families as $family => $styles) {
            foreach (['' => 'regular', 'B' => 'bold', 'I' => 'italic', 'BI' => 'boldItalic'] as $style => $slot) {
                $file = $styles[$slot];
                if ($file === null || $file === '') {
                    continue;
                }

                $faces[$family . $style] = new FontFace($family, $style, $file, FontCompiler::keyFor($file));
            }
        }

        return $faces;
    }

    public function roleOrDefault(?string $name): FontRole
    {
        $role = $name ?? 'regular';

        return $this->roles[$role] ?? new FontRole(array_key_first($this->families) ?: 'helvetica', '');
    }

    public function supportsStyle(string $family, string $style): bool
    {
        return $this->faceFile($family, strtoupper($style)) !== null;
    }

    public function cacheKey(string $family, string $style): ?string
    {
        $file = $this->faceFile($family, strtoupper($style));
        return $file === null ? null : FontCompiler::keyFor($file);
    }

    private function faceFile(string $family, string $style): ?string
    {
        if (!isset($this->families[$family])) {
            return null;
        }

        return match (strtoupper($style)) {
            'B' => $this->families[$family]['bold'],
            'I' => $this->families[$family]['italic'],
            'BI', 'IB' => $this->families[$family]['boldItalic'],
            default => $this->families[$family]['regular'],
        };
    }

    private static function styleLabel(string $style): string
    {
        return match ($style) {
            'B' => 'Bold',
            'I' => 'Italic',
            'BI', 'IB' => 'BoldItalic',
            default => 'Regular',
        };
    }
}
