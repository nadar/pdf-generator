<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Contract\PdfSettingsInterface;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Exception\MissingFontStyleException;
use Nadar\PdfGenerator\Exception\OverflowException;
use Nadar\PdfGenerator\Exception\TemplateNotFoundException;
use Nadar\PdfGenerator\Exception\TemplateSizeMismatchException;
use Nadar\PdfGenerator\Exception\UnknownMethodException;
use Nadar\PdfGenerator\Font\FontRegistry;
use Nadar\PdfGenerator\Support\Fields;
use Nadar\PdfGenerator\Support\TemplateInspector;
use Nadar\PdfGenerator\Support\Text;
use Nadar\PdfGenerator\Support\Units;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\PageSize;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

/** @mixin \setasign\Fpdi\Tcpdf\Fpdi */
final class PdfGenerator
{
    private Fpdi $pdf;

    private bool $debug;

    /** @var array<string,float> */
    private array $htmlMeasureCache = [];

    public function __construct(private readonly PdfSettingsInterface $settings)
    {
        $margins = $settings->margins();
        $this->pdf = new Fpdi($settings->pageOrientation(), 'mm', $settings->pageFormat());
        $this->pdf->SetMargins($margins->left, $margins->top, $margins->right);
        $this->pdf->SetAutoPageBreak(false, $margins->bottom());
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $this->pdf->setCellHeightRatio($settings->cellHeightRatio());
        $this->debug = $settings->debug();

        (new FontRegistry($settings->fontPath(), $settings->fontCachePath(), $settings->fonts()))->register($this->pdf);

        if ($settings->creator() !== '') {
            $this->creator($settings->creator());
        }

        if ($settings->author() !== '') {
            $this->author($settings->author());
        }

        $this->applyColor($settings->textColor());
        $this->selectFont(null, null);
    }

    public function raw(): Fpdi
    {
        return $this->pdf;
    }

    public function __call(string $method, array $args): mixed
    {
        if (!method_exists($this->pdf, $method)) {
            throw new UnknownMethodException($method);
        }

        return $this->pdf->{$method}(...$args);
    }

    public function creator(string $creator): static
    {
        $this->pdf->SetCreator($creator);

        return $this;
    }

    public function author(string $author): static
    {
        $this->pdf->SetAuthor($author);

        return $this;
    }

    public function title(string $title): static
    {
        $this->pdf->SetTitle($title);

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->pdf->SetSubject($subject);

        return $this;
    }

    public function keywords(string $keywords): static
    {
        $this->pdf->SetKeywords($keywords);

        return $this;
    }

    public function deterministic(int $timestamp): static
    {
        $this->pdf->setDocCreationTimestamp($timestamp);
        $this->pdf->setDocModificationTimestamp($timestamp);

        return $this;
    }

    public function debug(bool $enabled): static
    {
        $this->debug = $enabled;

        return $this;
    }

    public function page(?string $template = null, string|array|null $format = null, ?string $orientation = null, int $templatePage = 1, string $box = 'CropBox'): static
    {
        $resolvedFormat = $format ?? $this->settings->pageFormat();
        $resolvedOrientation = $orientation ?? $this->settings->pageOrientation();

        if ($template !== null && $format === null) {
            $size = $this->templateSize($template, $templatePage);
            $resolvedFormat = $size->asArray();
            $resolvedOrientation = $size->orientation();
        }

        $this->pdf->AddPage($resolvedOrientation, $resolvedFormat);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $this->pdf->setCellHeightRatio($this->settings->cellHeightRatio());

        if ($template !== null) {
            $this->stamp($template, $templatePage, $box);
        }

        $this->applyColor($this->settings->textColor());

        return $this;
    }

    public function stamp(string $template, int $sourcePage = 1, string $box = 'CropBox'): static
    {
        $path = $this->templateFile($template);
        $this->pdf->setSourceFile($path);
        $templateId = $this->pdf->importPage($sourcePage, $box);
        $size = $this->pdf->getTemplateSize($templateId);
        $this->pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

        return $this;
    }

    public function templateSize(string $template, int $page = 1): PageSize
    {
        return TemplateInspector::pageSize($this->templateFile($template), $page);
    }

    public function assertTemplateSize(string $template, float $w, float $h, float $tolerance = 0.05): static
    {
        $size = $this->templateSize($template);
        if (abs($size->width - $w) > $tolerance || abs($size->height - $h) > $tolerance) {
            throw new TemplateSizeMismatchException(sprintf(
                'Template "%s" size mismatch. Expected %.3f x %.3f mm, got %.3f x %.3f mm.',
                $template,
                $w,
                $h,
                $size->width,
                $size->height
            ));
        }

        return $this;
    }

    public function write(TextBox $box, string $text): static
    {
        $overflow = $box->overflow ?? $this->settings->overflow();
        if ($box->h === null && $overflow !== Overflow::None) {
            throw new OverflowException(sprintf('TextBox "%s" declares overflow policy with no height.', $box->id));
        }

        $text = Text::normalize($text);
        $this->selectFont($box->font, $box->size);
        $this->applyColor($box->color ?? $this->settings->textColor());

        if ($box->html) {
            $this->assertHtmlStyles($box, $text);
        }

        $renderBox = $box;
        if ($box->h !== null) {
            $renderBox = $this->applyOverflow($box, $text, $overflow);
        }

        $this->render($renderBox, $text);

        if ($this->debug) {
            $this->drawDebug($box, $text, $renderBox);
        }

        return $this;
    }

    public function writeText(float $x, float $y, float $w, string $text, ?float $h = null, ?string $font = null, ?float $size = null, string $align = 'L', ?Overflow $overflow = null): static
    {
        return $this->write(new TextBox('text', $x, $y, $w, $h, $font, $size, $align, null, $overflow), $text);
    }

    public function writeHtml(float $x, float $y, float $w, string $html, ?float $h = null, ?string $font = null, ?float $size = null, string $align = 'L', ?Overflow $overflow = null): static
    {
        return $this->write(new TextBox('html', $x, $y, $w, $h, $font, $size, $align, null, $overflow, html: true), $html);
    }

    public function writeRotated(float $x, float $y, float $w, float $angle, string $text, ?float $h = null, ?string $font = null, ?float $size = null, string $align = 'L', ?Overflow $overflow = null): static
    {
        $box = new TextBox('rotated', $x, $y, $w, $h, $font, $size, $align, null, $overflow, $angle);

        return $this->write($box, $text);
    }

    public function writeAll(iterable $boxes, array $data): static
    {
        foreach ($boxes as $id => $box) {
            $slotId = is_string($id) ? $id : $box->id;
            $this->write($box, Fields::get($data, $slotId));
        }

        return $this;
    }

    public function measureText(string $text, float $w, ?TextBox $box = null): float
    {
        $this->selectFont($box?->font, $box?->size);

        return (float) $this->pdf->getStringHeight($w, Text::normalize($text), false, true, 0, 0);
    }

    public function measureHtml(string $html, float $w, ?TextBox $box = null): float
    {
        $this->selectFont($box?->font, $box?->size);
        $x = $box?->x ?? $this->pdf->GetX();
        $y = $box?->y ?? $this->pdf->GetY();
        $align = $box?->align ?? 'L';

        $cacheKey = implode('|', [
            md5($html),
            $w,
            $this->pdf->getFontFamily(),
            $this->pdf->getFontStyle(),
            $this->pdf->getFontSizePt(),
            $align,
        ]);

        if (isset($this->htmlMeasureCache[$cacheKey])) {
            return $this->htmlMeasureCache[$cacheKey];
        }

        $this->pdf->startTransaction();
        $before = $this->pdf->GetY();
        $this->pdf->writeHTMLCell($w, null, $x, $y, $html, 0, 1, false, true, $align);
        $height = $this->pdf->GetY() - $before;
        $this->pdf->rollbackTransaction(true);

        $this->htmlMeasureCache[$cacheKey] = (float) $height;

        return (float) $height;
    }

    public function fits(TextBox $box, string $text): bool
    {
        if ($box->h === null) {
            return true;
        }

        $height = $box->html ? $this->measureHtml($text, $box->w, $box) : $this->measureText($text, $box->w, $box);

        return $height <= $box->h + 0.0001;
    }

    public function truncateToWidth(string $text, float $w, string $ellipsis = '...'): string
    {
        $plain = Text::normalize($text);
        if ($plain === '' || (float) $this->pdf->GetStringWidth($plain) <= $w) {
            return $plain;
        }

        $len = mb_strlen($plain);
        $low = 0;
        $high = $len;
        $best = '';
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $candidate = rtrim(mb_substr($plain, 0, $mid));
            $candidate = $this->wordBound($candidate);
            $candidateWithEllipsis = $candidate . $ellipsis;
            $width = (float) $this->pdf->GetStringWidth($candidateWithEllipsis);

            if ($width <= $w) {
                $best = $candidateWithEllipsis;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $best !== '' ? $best : $ellipsis;
    }

    public function append(string $pdfBytes): static
    {
        $stream = StreamReader::createByString($pdfBytes);
        $pages = $this->pdf->setSourceFile($stream);

        for ($i = 1; $i <= $pages; ++$i) {
            $tpl = $this->pdf->importPage($i);
            $size = $this->pdf->getTemplateSize($tpl);
            $this->pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
            $this->pdf->useTemplate($tpl);
        }

        return $this;
    }

    public function appendFile(string $path): static
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ConfigurationException(sprintf('Cannot append unreadable PDF "%s".', $path));
        }

        return $this->append((string) file_get_contents($path));
    }

    public function bytes(): string
    {
        return (string) $this->pdf->Output('', 'S');
    }

    public function save(string $path): string
    {
        $this->pdf->Output($path, 'F');

        return $path;
    }

    public function debugGrid(float $step): static
    {
        if (!$this->debug || $step <= 0) {
            return $this;
        }

        $size = $this->pdf->getPageDimensions();
        $width = (float) $size['wk'];
        $height = (float) $size['hk'];

        $this->pdf->SetLineStyle(['width' => 0.05, 'color' => [220, 220, 220]]);
        for ($x = 0.0; $x <= $width; $x += $step) {
            $this->pdf->Line($x, 0.0, $x, $height);
        }
        for ($y = 0.0; $y <= $height; $y += $step) {
            $this->pdf->Line(0.0, $y, $width, $y);
        }

        return $this;
    }

    private function applyOverflow(TextBox $box, string &$text, Overflow $overflow): TextBox
    {
        return match ($overflow) {
            Overflow::Shrink => $this->shrinkToFit($box, $text, false),
            Overflow::Clip => $box,
            Overflow::Truncate => $this->truncateToFit($box, $text),
            Overflow::ShrinkThenClip => $this->shrinkToFit($box, $text, true),
            Overflow::None => $box,
        };
    }

    private function shrinkToFit(TextBox $box, string $text, bool $allowClip): TextBox
    {
        $originalSize = $box->size ?? $this->settings->fontSize();
        $size = $originalSize;
        $iterations = 0;

        while ($size >= $box->minSize && $iterations < 200) {
            $candidate = $box->with(size: $size);
            if ($this->fits($candidate, $text)) {
                return $candidate;
            }
            $size -= 0.25;
            ++$iterations;
        }

        if ($allowClip) {
            return $box->with(size: max($box->minSize, $size + 0.25), overflow: Overflow::Clip);
        }

        throw new OverflowException(sprintf('Unable to shrink text in box "%s" to fit height %.3fmm.', $box->id, $box->h));
    }

    private function truncateToFit(TextBox $box, string &$text): TextBox
    {
        $text = $this->truncateToWidth($text, $box->w);

        return $box;
    }

    private function render(TextBox $box, string $text): void
    {
        if ($box->rotation !== 0.0) {
            $this->pdf->StartTransform();
            try {
                $this->pdf->Rotate($box->rotation, $box->x, $box->y);
                $this->renderCell($box, $text);
            } finally {
                $this->pdf->StopTransform();
            }

            return;
        }

        if (($box->overflow ?? $this->settings->overflow()) === Overflow::Clip && $box->h !== null) {
            $this->pdf->StartTransform();
            try {
                $this->pdf->Rect($box->x, $box->y, $box->w, $box->h, 'CNZ');
                $this->renderCell($box, $text);
            } finally {
                $this->pdf->StopTransform();
            }

            return;
        }

        $this->renderCell($box, $text);
    }

    private function renderCell(TextBox $box, string $text): void
    {
        if ($box->html) {
            $this->pdf->writeHTMLCell($box->w, $box->h, $box->x, $box->y, $text, 0, 1, false, true, $box->align);

            return;
        }

        $this->pdf->MultiCell($box->w, $box->h ?? 0, $text, 0, $box->align, false, 1, $box->x, $box->y, true, 0, false, true, 0, 'T');
    }

    private function drawDebug(TextBox $declared, string $text, TextBox $rendered): void
    {
        $this->pdf->SetDrawColor(255, 0, 255);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->Rect($declared->x, $declared->y, $declared->w, $declared->h ?? 3.0);
        $this->pdf->SetFont('helvetica', '', 4);
        $this->pdf->Text($declared->x, max(0, $declared->y - 0.8), $declared->id);

        $renderHeight = $rendered->html
            ? $this->measureHtml($text, $rendered->w, $rendered)
            : $this->measureText($text, $rendered->w, $rendered);

        $expectedHeight = $declared->h ?? $renderHeight;
        if (abs($renderHeight - $expectedHeight) > 0.05) {
            $this->pdf->SetDrawColor(0, 180, 255);
            $this->pdf->Rect($rendered->x, $rendered->y, $rendered->w, $renderHeight);
        }

        $this->selectFont($declared->font, $declared->size);
        $this->applyColor($declared->color ?? $this->settings->textColor());
    }

    private function selectFont(?string $roleName, ?float $size): void
    {
        $role = $this->settings->fonts()->roleOrDefault($roleName);
        $this->pdf->SetFont($role->family, $role->style, $size ?? $this->settings->fontSize());
    }

    private function applyColor(Color $color): void
    {
        $channels = $color->channels;
        if ($color->model === 'CMYK') {
            $this->pdf->SetTextColorArray([
                'C' => $channels[0],
                'M' => $channels[1],
                'Y' => $channels[2],
                'K' => $channels[3],
            ]);

            return;
        }

        if ($color->model === 'GRAY') {
            $gray = (int) round((float) $channels[0]);
            $this->pdf->SetTextColor($gray, $gray, $gray);

            return;
        }

        $this->pdf->SetTextColor((int) $channels[0], (int) $channels[1], (int) $channels[2]);
    }

    private function templateFile(string $template): string
    {
        $path = rtrim($this->settings->templatePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $template;

        if (!is_file($path) || !is_readable($path)) {
            throw new TemplateNotFoundException(sprintf('Template "%s" was not found in "%s".', $template, $this->settings->templatePath()));
        }

        return $path;
    }

    private function assertHtmlStyles(TextBox $box, string $markup): void
    {
        $needsBold = (bool) preg_match('/<(b|strong)\b/i', $markup);
        $needsItalic = (bool) preg_match('/<(i|em)\b/i', $markup);
        if (!$needsBold && !$needsItalic) {
            return;
        }

        $role = $this->settings->fonts()->roleOrDefault($box->font);
        $requiredStyle = ($needsBold ? 'B' : '') . ($needsItalic ? 'I' : '');
        $requiredStyle = $requiredStyle === '' ? $role->style : $requiredStyle;

        if (!$this->settings->fonts()->supportsStyle($role->family, $requiredStyle)) {
            $key = $this->settings->fonts()->cacheKey($role->family, $requiredStyle)
                ?? strtolower($role->family . $requiredStyle);
            $label = match ($requiredStyle) {
                'B' => 'Bold',
                'I' => 'Italic',
                'BI' => 'BoldItalic',
                default => 'Regular',
            };
            throw new MissingFontStyleException(sprintf(
                'Markup in "%s" needs %s for family "%s". Synthetic bold/italic is a no-op for embedded subset fonts. Add %s-%s.ttf (-> key "%s").',
                $box->id,
                $label,
                $role->family,
                ucfirst($role->family),
                $label,
                $key
            ));
        }
    }

    private function wordBound(string $text): string
    {
        $trimmed = rtrim($text);
        if ($trimmed === '') {
            return $trimmed;
        }

        $wordBound = (string) preg_replace('/\s+\S*$/u', '', $trimmed);

        return $wordBound !== '' ? $wordBound : $trimmed;
    }
}
