<?php

namespace Nadar\PdfGenerator;

use Nadar\PdfGenerator\Contract\PdfSettingsInterface;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Exception\MissingFontStyleException;
use Nadar\PdfGenerator\Exception\MissingImageException;
use Nadar\PdfGenerator\Exception\OverflowException;
use Nadar\PdfGenerator\Exception\TemplateNotFoundException;
use Nadar\PdfGenerator\Exception\TemplateSizeMismatchException;
use Nadar\PdfGenerator\Exception\UnknownMethodException;
use Nadar\PdfGenerator\Font\FontRegistry;
use Nadar\PdfGenerator\Font\FontRole;
use Nadar\PdfGenerator\Support\Fields;
use Nadar\PdfGenerator\Support\ImageLoader;
use Nadar\PdfGenerator\Support\TemplateInspector;
use Nadar\PdfGenerator\Support\Text;
use Nadar\PdfGenerator\Support\Units;
use Nadar\PdfGenerator\Tcpdf\MetricsFpdi;
use Nadar\PdfGenerator\Value\Color;
use Nadar\PdfGenerator\Value\PageSize;
use Nadar\PdfGenerator\Value\Rect;
use Nadar\PdfGenerator\Value\TextMetrics;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * A typed facade over TCPDF/FPDI for fixed-layout, template-overlay documents.
 *
 * ## Conventions
 *
 * - Units are **millimetres**; the origin is the **top-left** of the page.
 * - Font sizes are in **points**.
 * - `TextBox::y` is the top of the first line's *cell* unless the box declares
 *   another {@see Anchor}. See {@see probe()} to read the resolved geometry -
 *   including the baseline - back out.
 * - Template names passed to {@see page()} and {@see stamp()} are basenames
 *   relative to the settings' `templatePath()`.
 *
 * ```php
 * $pdf = (new PdfGenerator(new BrandPdfSettings()))
 *     ->title('June highlights')
 *     ->page('poster-template.pdf');
 *
 * $pdf->write(new TextBox('month', x: 20, y: 28.4, w: 170, size: 25, align: Align::Center), 'June');
 * $pdf->image(ImageBox::circle('photo', cx: 30.3, cy: 68.3, diameter: 30.9), $event['image']);
 * $pdf->qr($event['url'], x: 179, y: 58.6, size: 19.5, color: Color::hex('#223764'));
 *
 * file_put_contents('poster.pdf', $pdf->bytes());
 * ```
 *
 * Anything not covered here is reachable through {@see raw()}, and unknown
 * method calls proxy to the underlying document.
 *
 * @mixin \setasign\Fpdi\Tcpdf\Fpdi
 */
final class PdfGenerator
{
    private Fpdi $pdf;

    private bool $debug;

    private ImageLoader $images;

    private ?TextMetrics $lastMetrics = null;

    /** @var array<string,float> */
    private array $htmlMeasureCache = [];

    /**
     * @throws \Nadar\PdfGenerator\Exception\FontException            when a font path is missing
     * @throws \Nadar\PdfGenerator\Exception\FontCacheMissingException when a face was never compiled
     */
    public function __construct(private readonly PdfSettingsInterface $settings)
    {
        $margins = $settings->margins();
        $factory = $settings->pdfFactory();

        $this->pdf = $factory !== null
            ? $factory->create($settings->pageOrientation(), 'mm', $settings->pageFormat())
            : new MetricsFpdi($settings->pageOrientation(), 'mm', $settings->pageFormat());

        $this->pdf->SetMargins($margins->left, $margins->top, $margins->right);
        $this->pdf->SetAutoPageBreak(false, $margins->bottom());
        $this->applyPageDefaults();
        $this->debug = $settings->debug();
        $this->images = new ImageLoader();

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

    /**
     * The underlying FPDI document, for anything this facade does not wrap.
     *
     * Everything reachable here is documented TCPDF API; reaching for it is not
     * a failure, it is the escape hatch working as intended.
     */
    public function raw(): Fpdi
    {
        return $this->pdf;
    }

    /** The image resolver, exposed so its cache can be inspected or cleared. */
    public function imageLoader(): ImageLoader
    {
        return $this->images;
    }

    /**
     * Proxy unknown calls to the underlying document.
     *
     * @param list<mixed> $args
     *
     * @throws UnknownMethodException when neither this class nor TCPDF has the method
     */
    public function __call(string $method, array $args): mixed
    {
        if (!method_exists($this->pdf, $method)) {
            throw new UnknownMethodException($method);
        }

        return $this->pdf->{$method}(...$args);
    }

    /** Set the PDF `Creator` metadata field. */
    public function creator(string $creator): static
    {
        $this->pdf->SetCreator($creator);

        return $this;
    }

    /** Set the PDF `Author` metadata field. */
    public function author(string $author): static
    {
        $this->pdf->SetAuthor($author);

        return $this;
    }

    /** Set the PDF `Title` metadata field. */
    public function title(string $title): static
    {
        $this->pdf->SetTitle($title);

        return $this;
    }

    /** Set the PDF `Subject` metadata field. */
    public function subject(string $subject): static
    {
        $this->pdf->SetSubject($subject);

        return $this;
    }

    /** Set the PDF `Keywords` metadata field. */
    public function keywords(string $keywords): static
    {
        $this->pdf->SetKeywords($keywords);

        return $this;
    }

    /**
     * Pin the creation and modification timestamps so output is byte-stable.
     *
     * Two renders of the same input normally differ, because TCPDF stamps the
     * current time (and a derived document id) into every file. Pinning the
     * timestamp makes the bytes reproducible, which is what enables:
     *
     * - **golden-file tests** - assert the output hash, and a font or layout
     *   change that silently reflows a print run fails the build;
     * - **HTTP caching** - a stable `ETag`/`Last-Modified` for a generated
     *   document that has not actually changed.
     *
     * Use a timestamp derived from the *content* (the record's `updated_at`,
     * say), not `time()`.
     *
     * Besides the timestamps, this pins the document id that TCPDF otherwise
     * seeds randomly and writes into the XMP metadata - without which the bytes
     * would still differ on every render. That part needs the default document
     * class: a custom {@see \Nadar\PdfGenerator\Contract\PdfFactoryInterface}
     * returning something that does not extend {@see MetricsFpdi} gets stable
     * timestamps but not fully stable bytes.
     *
     * @param int         $timestamp unix timestamp
     * @param null|string $id        seed for the document id; defaults to the timestamp.
     *                               Pass something content-derived to distinguish
     *                               documents rendered at the same timestamp.
     */
    public function deterministic(int $timestamp, ?string $id = null): static
    {
        $this->pdf->setDocCreationTimestamp($timestamp);
        $this->pdf->setDocModificationTimestamp($timestamp);

        if ($this->pdf instanceof MetricsFpdi) {
            $this->pdf->pinFileId($id ?? (string) $timestamp);
        }

        return $this;
    }

    /**
     * Toggle debug drawing: box outlines, the first baseline, and the resolved
     * size of every subsequent {@see write()}.
     *
     * Development only - the marks are drawn into the document.
     */
    public function debug(bool $enabled): static
    {
        $this->debug = $enabled;

        return $this;
    }

    /**
     * Start a page, optionally stamping a template onto it.
     *
     * With a template and no explicit `$format`, the page size and orientation
     * are derived from the template - which is almost always what a
     * template overlay wants.
     *
     * @param null|string             $template basename inside the settings' `templatePath()`
     * @param null|string|list<float> $format   named format or `[width, height]` in mm;
     *                                          defaults to the template's size, else the settings'
     * @param null|string             $orientation `P` or `L`; defaults as for `$format`
     * @param int                     $templatePage which page of the template to stamp
     * @param string                  $box      the PDF box to import. `CropBox` is the visible
     *                                          trimmed area and the right default; Canva and
     *                                          InDesign exports often have `CropBox` != `MediaBox`,
     *                                          so switch to `MediaBox` only if bleed must be kept.
     *
     * @throws TemplateNotFoundException when the template file is missing
     */
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
        $this->applyPageDefaults();

        if ($template !== null) {
            $this->stamp($template, $templatePage, $box);
        }

        $this->applyColor($this->settings->textColor());

        return $this;
    }

    /**
     * Stamp a template page onto the current page, at full size from (0, 0).
     *
     * @param string $template basename inside the settings' `templatePath()`
     * @param int    $sourcePage which page of the template to import
     * @param string $box      see {@see page()}
     *
     * @throws TemplateNotFoundException when the template file is missing
     * @throws ConfigurationException    when the imported page size cannot be read
     */
    public function stamp(string $template, int $sourcePage = 1, string $box = 'CropBox'): static
    {
        $path = $this->templateFile($template);
        $this->pdf->setSourceFile($path);
        $templateId = $this->pdf->importPage($sourcePage, $box);
        $size = $this->pdf->getTemplateSize($templateId);
        if (!is_array($size)) {
            throw new ConfigurationException(sprintf('Unable to read imported template size for "%s".', $template));
        }
        $this->pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

        return $this;
    }

    /**
     * The page size of a template, in mm.
     *
     * @throws TemplateNotFoundException when the template file is missing
     */
    public function templateSize(string $template, int $page = 1): PageSize
    {
        return TemplateInspector::pageSize($this->templateFile($template), $page);
    }

    /**
     * Assert a template's size, so a re-export at the wrong page size fails
     * loudly instead of shifting every coordinate.
     *
     * @param float $w         expected width in mm
     * @param float $h         expected height in mm
     * @param float $tolerance allowed deviation in mm
     *
     * @throws TemplateSizeMismatchException when the size differs by more than $tolerance
     */
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

    /**
     * Write text (or HTML, when the box says so) into a slot.
     *
     * Applies the box's {@see Overflow} policy, translates its {@see Anchor} to
     * TCPDF's cell top, and records {@see lastMetrics()}.
     *
     * @throws OverflowException        when the box declares a policy but no height, or
     *                                  when {@see Overflow::Shrink} cannot make the text fit
     * @throws MissingFontStyleException when HTML markup needs a face that is not registered
     * @throws ConfigurationException   when `Anchor::CapHeight` is used with a document
     *                                  class that cannot report cap height
     */
    public function write(TextBox $box, string $text): static
    {
        $overflow = $this->effectiveOverflow($box);

        $text = Text::normalize($text);
        $this->selectFont($box->font, $box->size);
        $this->applyColor($box->color ?? $this->settings->textColor());

        if ($box->html) {
            $this->assertHtmlStyles($box, $text);
        }

        $resolved = $box->h !== null ? $this->applyOverflow($box, $text, $overflow) : $box;
        $renderPolicy = $resolved->overflow ?? $overflow;
        $placed = $this->applyAnchor($resolved);

        $this->selectFont($placed->font, $placed->size);
        $this->render($placed, $text, $renderPolicy);

        $this->lastMetrics = $this->metricsFor($box, $placed, $text, $renderPolicy);

        if ($this->debug) {
            $this->drawDebug($box, $placed, $this->lastMetrics);
            $this->selectFont($box->font, $box->size);
            $this->applyColor($box->color ?? $this->settings->textColor());
        }

        return $this;
    }

    /**
     * Resolve a box against some text **without drawing anything**.
     *
     * This is what closes the calibration loop in-process: ask where the text
     * would actually land, compare against the reference, correct the constant.
     * No `pdftoppm`, no pixel diffing, no external tooling.
     *
     * ```php
     * $m = $pdf->probe($titleBox, $event['title']);
     * // $m->baseline is the first baseline in mm; $m->size the size after shrink
     * ```
     *
     * A page must already exist - call {@see page()} first.
     *
     * @throws ConfigurationException when `Anchor::CapHeight` is unavailable
     */
    public function probe(TextBox $box, string $text): TextMetrics
    {
        $family = $this->pdf->getFontFamily();
        $style = $this->pdf->getFontStyle();
        $size = (float) $this->pdf->getFontSizePt();

        try {
            $overflow = $this->effectiveOverflow($box);
            $probeText = Text::normalize($text);

            $this->selectFont($box->font, $box->size);

            $resolved = $box->h !== null && $overflow !== Overflow::None
                ? $this->applyOverflow($box, $probeText, $overflow)
                : $box;

            return $this->metricsFor($box, $this->applyAnchor($resolved), $probeText, $resolved->overflow ?? $overflow);
        } finally {
            $this->pdf->SetFont($family, $style, $size);
        }
    }

    /**
     * Geometry of the most recent {@see write()}, or `null` before the first one.
     *
     * The resolved font size is the interesting part when an overflow policy is
     * in play: it says how far the text actually had to shrink.
     */
    public function lastMetrics(): ?TextMetrics
    {
        return $this->lastMetrics;
    }

    /**
     * Write plain text without building a {@see TextBox} first.
     *
     * @param Align|string $align see {@see TextBox::__construct()}
     */
    public function writeText(float $x, float $y, float $w, string $text, ?float $h = null, ?string $font = null, ?float $size = null, Align|string $align = Align::Left, ?Overflow $overflow = null): static
    {
        return $this->write(new TextBox('text', $x, $y, $w, $h, $font, $size, $align, null, $overflow), $text);
    }

    /**
     * Write a small subset of HTML (`<b>`, `<i>`, `<br/>`, ...) without building
     * a {@see TextBox} first.
     *
     * Bold and italic markup requires the corresponding face to be registered;
     * see {@see MissingFontStyleException}.
     *
     * @param Align|string $align see {@see TextBox::__construct()}
     */
    public function writeHtml(float $x, float $y, float $w, string $html, ?float $h = null, ?string $font = null, ?float $size = null, Align|string $align = Align::Left, ?Overflow $overflow = null): static
    {
        return $this->write(new TextBox('html', $x, $y, $w, $h, $font, $size, $align, null, $overflow, html: true), $html);
    }

    /**
     * Write text rotated around its own ($x, $y).
     *
     * @param float $angle degrees; positive turns counter-clockwise, as in TCPDF
     * @param Align|string $align see {@see TextBox::__construct()}
     */
    public function writeRotated(float $x, float $y, float $w, float $angle, string $text, ?float $h = null, ?string $font = null, ?float $size = null, Align|string $align = Align::Left, ?Overflow $overflow = null): static
    {
        return $this->write(new TextBox('rotated', $x, $y, $w, $h, $font, $size, $align, null, $overflow, $angle), $text);
    }

    /**
     * Fill a whole {@see Layout} from keyed data.
     *
     * Each slot's id is the data key. Keys are matched with `-`/`_`
     * interchangeable, and a missing key writes an empty string rather than
     * failing - a half-filled document is easier to debug than an exception.
     *
     * @param iterable<int|string,TextBox> $boxes a {@see Layout}, or any iterable of boxes.
     *                                            String keys override the box's own id.
     * @param array<string,mixed>          $data
     */
    public function writeAll(iterable $boxes, array $data): static
    {
        foreach ($boxes as $id => $box) {
            $slotId = is_string($id) ? $id : $box->id;
            $this->write($box, Fields::get($data, $slotId));
        }

        return $this;
    }

    /**
     * Place an image, scaled and clipped to its {@see ImageBox}.
     *
     * Replaces the aspect-ratio arithmetic, the `'CNZ'` clipping path and the
     * long positional `Image()` call that a template overlay would otherwise
     * need. Each distinct source is fetched **once** per document, so reading
     * the dimensions does not cost a second download.
     *
     * Missing sources are expected in production; they resolve in this order:
     *
     * 1. `$onMissing` callback, if given - full control over the fallback;
     * 2. the box's `placeholder` colour, drawn in the box's own shape;
     * 3. otherwise a {@see MissingImageException}.
     *
     * @param null|string $source local path or `http(s)` URL; `null` counts as missing
     * @param null|callable(ImageBox, self):void $onMissing draws the fallback itself
     *
     * @throws MissingImageException when the source cannot be read and nothing handles it
     */
    public function image(ImageBox $box, ?string $source, ?callable $onMissing = null): static
    {
        $failure = null;
        $resolved = null;

        if ($source === null || trim($source) === '') {
            $failure = sprintf('No image source given for box "%s".', $box->id);
        } else {
            try {
                $resolved = $this->images->load($source);
            } catch (MissingImageException $exception) {
                $failure = sprintf('Image for box "%s" is unavailable: %s', $box->id, $exception->getMessage());
            }
        }

        if ($resolved === null) {
            if ($onMissing !== null) {
                $onMissing($box, $this);

                return $this;
            }

            if ($box->placeholder !== null) {
                $this->fillShape($box, $box->placeholder);

                return $this;
            }

            throw new MissingImageException((string) $failure);
        }

        [$x, $y, $w, $h] = $box->placement($resolved->width, $resolved->height);
        $needsTransform = $box->shape->kind !== ShapeKind::Rect || $box->rotation !== 0.0;

        if ($needsTransform) {
            $this->pdf->StartTransform();
        }

        try {
            if ($box->rotation !== 0.0) {
                [$cx, $cy] = $box->bounds()->center();
                $this->pdf->Rotate($box->rotation, $cx, $cy);
            }

            if ($box->shape->kind !== ShapeKind::Rect) {
                $this->clipToShape($box);
            }

            $this->pdf->Image(
                $resolved->reference(),
                $x,
                $y,
                $w,
                $h,
                '',
                '',
                '',
                false,
                $box->dpi,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
        } finally {
            if ($needsTransform) {
                $this->pdf->StopTransform();
            }
        }

        if ($this->debug) {
            $this->drawDebugRect($box->bounds(), $box->id);
        }

        return $this;
    }

    /**
     * Draw a QR code.
     *
     * The defaults are the ones a designed template wants: a **transparent**
     * background and **no quiet zone**, so the code sits directly on the
     * artwork instead of punching a white tile through it, and its rendered
     * size equals the size measured off the design. Scanners need contrast, not
     * black - so brand-coloured modules on a mid-tone background still read.
     *
     * ```php
     * $pdf->qr($event['url'], x: 179, y: 58.6, size: 19.5, color: Color::hex('#223764'));
     * ```
     *
     * @param string     $data      the payload, usually a URL
     * @param float      $size      width and height of the square, in mm
     * @param null|Color $color     module colour; `null` means black
     * @param null|Color $background background fill; `null` means transparent
     * @param EccLevel   $level     error correction. `M` is the right trade-off around
     *                              20 mm with a full URL; `H` noticeably densifies the grid.
     * @param int        $quietZone margin **in modules** (TCPDF's own "auto" is 4). Zero
     *                              lets the design's whitespace serve as the quiet zone.
     *
     * @throws ConfigurationException on empty data or a non-positive size
     */
    public function qr(
        string $data,
        float $x,
        float $y,
        float $size,
        ?Color $color = null,
        ?Color $background = null,
        EccLevel $level = EccLevel::M,
        int $quietZone = 0
    ): static {
        if (trim($data) === '') {
            throw new ConfigurationException('Cannot render a QR code for empty data.');
        }

        if ($size <= 0) {
            throw new ConfigurationException(sprintf('QR size must be positive, %.3f mm given.', $size));
        }

        if ($quietZone < 0) {
            throw new ConfigurationException(sprintf('QR quiet zone must not be negative, %d given.', $quietZone));
        }

        $this->withPreservedCursor(function () use ($data, $x, $y, $size, $color, $background, $level, $quietZone): void {
            $this->pdf->write2DBarcode(
                $data,
                'QRCODE,' . $level->value,
                $x,
                $y,
                $size,
                $size,
                [
                    'border' => false,
                    'padding' => $quietZone,
                    'fgcolor' => ($color ?? Color::black())->toArray(),
                    'bgcolor' => $background === null ? false : $background->toArray(),
                ],
                'N'
            );
        });

        if ($this->debug) {
            $this->drawDebugRect(new Rect($x, $y, $size, $size), 'qr');
        }

        return $this;
    }

    /**
     * Draw a linear (1D) barcode - the invoice and logistics case.
     *
     * Like {@see qr()}, defaults to a transparent background and no padding.
     *
     * @param string     $data       the payload; must be valid for $type
     * @param Barcode1D  $type       symbology
     * @param float      $w          total width in mm
     * @param float      $h          bar height in mm, excluding any text line
     * @param null|Color $color      bar (and text) colour; `null` means black
     * @param null|Color $background background fill; `null` means transparent
     * @param bool       $showText   print the human-readable line under the bars
     * @param float      $padding    margin around the bars, **in mm** (unlike {@see qr()},
     *                               where TCPDF counts modules)
     *
     * @throws ConfigurationException on empty data or non-positive dimensions
     */
    public function barcode1d(
        string $data,
        Barcode1D $type,
        float $x,
        float $y,
        float $w,
        float $h,
        ?Color $color = null,
        ?Color $background = null,
        bool $showText = false,
        float $padding = 0.0
    ): static {
        if (trim($data) === '') {
            throw new ConfigurationException('Cannot render a barcode for empty data.');
        }

        if ($w <= 0 || $h <= 0) {
            throw new ConfigurationException(sprintf('Barcode size must be positive, got %.3f x %.3f mm.', $w, $h));
        }

        $this->withPreservedCursor(function () use ($data, $type, $x, $y, $w, $h, $color, $background, $showText, $padding): void {
            $this->pdf->write1DBarcode(
                $data,
                $type->value,
                $x,
                $y,
                $w,
                $h,
                null,
                [
                    'border' => false,
                    'padding' => $padding,
                    'fgcolor' => ($color ?? Color::black())->toArray(),
                    'bgcolor' => $background === null ? false : $background->toArray(),
                    'text' => $showText,
                    'stretch' => true,
                ],
                'N'
            );
        });

        if ($this->debug) {
            $this->drawDebugRect(new Rect($x, $y, $w, $h), $type->value);
        }

        return $this;
    }

    /**
     * Height in mm that plain text occupies at a given width.
     *
     * @param null|TextBox $box selects the font/size to measure with; `null` uses the current one
     */
    public function measureText(string $text, float $w, ?TextBox $box = null): float
    {
        $this->selectFont($box?->font, $box?->size);

        return (float) $this->pdf->getStringHeight($w, Text::normalize($text), false, true, null, 0);
    }

    /** Height in mm that rendered HTML occupies at a given width. Results are cached. */
    public function measureHtml(string $html, float $w, ?TextBox $box = null): float
    {
        $this->selectFont($box?->font, $box?->size);
        $x = $box !== null ? $box->x : $this->pdf->GetX();
        $y = $box !== null ? $box->y : $this->pdf->GetY();
        $align = $box !== null ? $box->align->value : Align::Left->value;

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
        $this->pdf->writeHTMLCell($w, 0.0, $x, $y, $html, 0, 1, false, true, $align);
        $height = $this->pdf->GetY() - $before;
        $this->pdf->rollbackTransaction(true);

        $this->htmlMeasureCache[$cacheKey] = (float) $height;

        return (float) $height;
    }

    /**
     * How many lines the text wraps into at a given width.
     *
     * For HTML boxes this is derived from the measured height, so it is an
     * estimate rather than an exact count.
     */
    public function lineCount(string $text, float $w, ?TextBox $box = null): int
    {
        $this->selectFont($box?->font, $box?->size);
        $normalized = Text::normalize($text);

        if ($normalized === '') {
            return 0;
        }

        if ($box !== null && $box->html) {
            $lineHeight = $this->lineHeight($this->effectiveSize($box));

            return $lineHeight > 0
                ? max(1, (int) round($this->measureHtml($normalized, $w, $box) / $lineHeight))
                : 1;
        }

        return max(1, (int) $this->pdf->getNumLines($normalized, $w, false, true, null, 0));
    }

    /** Whether the text fits the box's declared height. Always true without a height. */
    public function fits(TextBox $box, string $text): bool
    {
        if ($box->h === null) {
            return true;
        }

        $height = $box->html ? $this->measureHtml($text, $box->w, $box) : $this->measureText($text, $box->w, $box);

        return $height <= $box->h + 0.0001;
    }

    /**
     * Cut text at a word boundary so it plus $ellipsis fits within $w mm.
     *
     * Measured with the currently selected font.
     */
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

    /**
     * Append every page of another PDF, each at its own size.
     *
     * @param string $pdfBytes a complete PDF document
     *
     * @throws ConfigurationException when a page size cannot be read
     */
    public function append(string $pdfBytes): static
    {
        $stream = StreamReader::createByString($pdfBytes);
        $pages = $this->pdf->setSourceFile($stream);

        for ($i = 1; $i <= $pages; ++$i) {
            $tpl = $this->pdf->importPage($i);
            $size = $this->pdf->getTemplateSize($tpl);
            if (!is_array($size)) {
                throw new ConfigurationException('Unable to read imported appended template size.');
            }
            $this->pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
            $this->pdf->useTemplate($tpl);
        }

        return $this;
    }

    /**
     * Append every page of a PDF file.
     *
     * @throws ConfigurationException when the file cannot be read
     */
    public function appendFile(string $path): static
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ConfigurationException(sprintf('Cannot append unreadable PDF "%s".', $path));
        }

        return $this->append((string) file_get_contents($path));
    }

    /**
     * Render and return the document bytes.
     *
     * Closes the document: nothing may be written afterwards.
     */
    public function bytes(): string
    {
        return (string) $this->pdf->Output('', 'S');
    }

    /**
     * Render and write the document to a file, returning the path.
     *
     * Closes the document: nothing may be written afterwards.
     */
    public function save(string $path): string
    {
        $this->pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Draw a measuring grid over the current page, when debug is enabled.
     *
     * @param float $step grid spacing in mm
     */
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

    /**
     * The policy that actually applies to a box.
     *
     * A policy declared *on the box* with no height is a mistake worth
     * reporting - there is nothing for it to act on. The settings' default is
     * different: it exists so height-constrained slots do not each have to
     * repeat it, and must not make every heightless box an error.
     *
     * @throws OverflowException when the box itself declares a policy but no height
     */
    private function effectiveOverflow(TextBox $box): Overflow
    {
        if ($box->h !== null) {
            return $box->overflow ?? $this->settings->overflow();
        }

        if ($box->overflow !== null && $box->overflow !== Overflow::None) {
            throw new OverflowException(sprintf(
                'TextBox "%s" declares overflow policy %s but no height; there is nothing to overflow. '
                . 'Give it an "h", or drop the policy.',
                $box->id,
                $box->overflow->name
            ));
        }

        return Overflow::None;
    }

    private function applyPageDefaults(): void
    {
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $this->pdf->setCellHeightRatio($this->settings->cellHeightRatio());
    }

    /**
     * Resolve a box against its overflow policy, rewriting $text where the
     * policy calls for it.
     */
    private function applyOverflow(TextBox $box, string &$text, Overflow $overflow): TextBox
    {
        $resolved = match ($overflow) {
            Overflow::Shrink => $this->shrinkToFit($box, $text, false),
            Overflow::ShrinkThenClip => $this->shrinkToFit($box, $text, true),
            Overflow::Truncate => $this->truncateToFit($box, $text),
            Overflow::Clip, Overflow::None => $box,
        };

        // Shrinking alone cannot always reach the line cap - the floor may be
        // hit first. Clipping there would leave a sliver of the next line
        // showing, so drop the surplus words instead.
        if ($box->maxLines !== null && $overflow !== Overflow::None) {
            $text = $this->truncateToLines($text, $resolved, $box->maxLines);
        }

        return $resolved;
    }

    private function shrinkToFit(TextBox $box, string $text, bool $allowClip): TextBox
    {
        $originalSize = $this->effectiveSize($box);
        $floor = $box->minSizeFor($originalSize);
        $size = $originalSize;
        $iterations = 0;

        while ($size >= $floor && $iterations < 400) {
            $candidate = $box->with(size: $size);
            if ($this->fits($candidate, $text) && $this->respectsMaxLines($candidate, $text)) {
                return $candidate;
            }
            $size -= 0.25;
            ++$iterations;
        }

        if ($allowClip) {
            return $box->with(size: max($floor, $size + 0.25), overflow: Overflow::Clip);
        }

        throw new OverflowException(sprintf(
            'Unable to fit text in box "%s": %s at sizes from %.2fpt down to the %.2fpt floor. '
            . 'Raise the box height%s, lower minSize, or use Overflow::ShrinkThenClip - which clips '
            . 'the remainder instead of throwing, and is the safer choice when iterating data.',
            $box->id,
            $box->maxLines !== null
                ? sprintf('could not reach %d line(s) within %.3fmm', $box->maxLines, (float) $box->h)
                : sprintf('does not fit %.3fmm', (float) $box->h),
            $originalSize,
            $floor,
            $box->maxLines !== null ? ' or maxLines' : ''
        ));
    }

    private function truncateToFit(TextBox $box, string &$text): TextBox
    {
        $text = $this->truncateToWidth($text, $box->w);

        return $box;
    }

    /**
     * Drop trailing words until the text wraps into at most $maxLines lines.
     *
     * Measured with the box's own font and size, so it must run after any
     * shrinking. An ellipsis marks the cut, as with {@see Overflow::Truncate}.
     */
    private function truncateToLines(string $text, TextBox $box, int $maxLines, string $ellipsis = '...'): string
    {
        if ($text === '' || $this->lineCount($text, $box->w, $box) <= $maxLines) {
            return $text;
        }

        $low = 0;
        $high = mb_strlen($text);
        $best = '';

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $candidate = $this->wordBound(rtrim(mb_substr($text, 0, $mid))) . $ellipsis;

            if ($this->lineCount($candidate, $box->w, $box) <= $maxLines) {
                $best = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $best !== '' ? $best : $ellipsis;
    }

    private function respectsMaxLines(TextBox $box, string $text): bool
    {
        if ($box->maxLines === null) {
            return true;
        }

        return $this->lineCount($text, $box->w, $box) <= $box->maxLines;
    }

    /**
     * Translate a box's anchor into TCPDF's cell-top reference.
     *
     * With `cellHeight = size(mm) * cellHeightRatio` and
     * `halfLeading = (cellHeight - ascent - descent) / 2`, the first baseline of
     * a cell drawn at `y` sits at `y + halfLeading + ascent`. Anchoring inverts
     * that relation.
     */
    private function applyAnchor(TextBox $box): TextBox
    {
        if ($box->anchor === Anchor::Top) {
            return $box;
        }

        $size = $this->effectiveSize($box);
        $role = $this->resolveRole($box->font);
        $ascent = $this->fontAscent($role, $size);
        $halfLeading = ($this->lineHeight($size) - $ascent - $this->fontDescent($role, $size)) / 2;

        // Anchor::Top returned above, so only the two ink-relative anchors remain.
        $cellTop = $box->anchor === Anchor::Baseline
            ? $box->y - $halfLeading - $ascent
            : $box->y + $this->capHeight($role, $size) - $halfLeading - $ascent;

        return $box->with(y: $cellTop, anchor: Anchor::Top);
    }

    /**
     * @param TextBox $declared the box as written by the caller
     * @param TextBox $placed   after overflow resolution and anchor translation
     */
    private function metricsFor(TextBox $declared, TextBox $placed, string $text, Overflow $overflow): TextMetrics
    {
        $size = $this->effectiveSize($placed);
        $role = $this->resolveRole($placed->font);
        $ascent = $this->fontAscent($role, $size);
        $descent = $this->fontDescent($role, $size);
        $lineHeight = $this->lineHeight($size);

        $height = $placed->html
            ? $this->measureHtml($text, $placed->w, $placed)
            : $this->measureText($text, $placed->w, $placed);

        return new TextMetrics(
            new Rect($placed->x, $placed->y, $placed->w, $declared->h ?? $height),
            $placed->y + ($lineHeight - $ascent - $descent) / 2 + $ascent,
            $size,
            $this->lineCount($text, $placed->w, $placed),
            $lineHeight,
            $height,
            $ascent,
            $descent,
            $declared->h === null || $height <= $declared->h + 0.0001,
            $overflow
        );
    }

    private function render(TextBox $box, string $text, Overflow $overflow): void
    {
        $clip = $overflow === Overflow::Clip && $box->h !== null;
        $rotate = $box->rotation !== 0.0;

        if (!$clip && !$rotate) {
            $this->renderCell($box, $text);

            return;
        }

        $this->pdf->StartTransform();
        try {
            if ($rotate) {
                $this->pdf->Rotate($box->rotation, $box->x, $box->y);
            }
            if ($clip) {
                $this->pdf->Rect($box->x, $box->y, $box->w, (float) $box->h, 'CNZ');
            }
            $this->renderCell($box, $text);
        } finally {
            $this->pdf->StopTransform();
        }
    }

    private function renderCell(TextBox $box, string $text): void
    {
        if ($box->html) {
            $this->pdf->writeHTMLCell($box->w, $box->h ?? 0.0, $box->x, $box->y, $text, 0, 1, false, true, $box->align->value);

            return;
        }

        $this->pdf->MultiCell($box->w, $box->h ?? 0, $text, 0, $box->align->value, false, 1, $box->x, $box->y, true, 0, false, true, 0, 'T');
    }

    private function clipToShape(ImageBox $box): void
    {
        [$cx, $cy] = $box->bounds()->center();

        match ($box->shape->kind) {
            ShapeKind::Circle => $this->pdf->Ellipse($cx, $cy, $box->w / 2, $box->h / 2, 0, 0, 360, 'CNZ'),
            ShapeKind::RoundRect => $this->pdf->RoundedRect(
                $box->x,
                $box->y,
                $box->w,
                $box->h,
                min($box->shape->radius, min($box->w, $box->h) / 2),
                '1111',
                'CNZ'
            ),
            ShapeKind::Rect => $this->pdf->Rect($box->x, $box->y, $box->w, $box->h, 'CNZ'),
        };
    }

    private function fillShape(ImageBox $box, Color $color): void
    {
        [$cx, $cy] = $box->bounds()->center();
        $channels = $color->toArray();

        match ($box->shape->kind) {
            ShapeKind::Circle => $this->pdf->Ellipse($cx, $cy, $box->w / 2, $box->h / 2, 0, 0, 360, 'F', [], $channels),
            ShapeKind::RoundRect => $this->pdf->RoundedRect(
                $box->x,
                $box->y,
                $box->w,
                $box->h,
                min($box->shape->radius, min($box->w, $box->h) / 2),
                '1111',
                'F',
                [],
                $channels
            ),
            ShapeKind::Rect => $this->pdf->Rect($box->x, $box->y, $box->w, $box->h, 'F', [], $channels),
        };
    }

    /** Run a drawing callback without letting it move TCPDF's cursor. */
    private function withPreservedCursor(callable $draw): void
    {
        $x = $this->pdf->GetX();
        $y = $this->pdf->GetY();

        try {
            $draw();
        } finally {
            $this->pdf->SetXY($x, $y);
        }
    }

    private function drawDebug(TextBox $declared, TextBox $placed, TextMetrics $metrics): void
    {
        $this->drawDebugRect(new Rect($declared->x, $declared->y, $declared->w, $declared->h ?? $metrics->height), $declared->id);

        // The baseline: what a design's coordinates usually refer to.
        $this->pdf->SetDrawColor(0, 180, 255);
        $this->pdf->SetLineWidth(0.08);
        $this->pdf->Line($placed->x, $metrics->baseline, $placed->x + $placed->w, $metrics->baseline);

        // Resolved size and line count, so a shrink is visible rather than inferred.
        $this->pdf->SetFont('helvetica', '', 4);
        $this->pdf->SetTextColor(0, 140, 200);
        $this->pdf->Text(
            $declared->x,
            max(0.0, $metrics->box->bottom() + 0.2),
            sprintf('%.2fpt / %d line(s) / base %.2f', $metrics->size, $metrics->lines, $metrics->baseline)
        );
    }

    private function drawDebugRect(Rect $rect, string $label): void
    {
        $this->pdf->SetDrawColor(255, 0, 255);
        $this->pdf->SetLineWidth(0.1);
        $this->pdf->Rect($rect->x, $rect->y, $rect->w, max(0.1, $rect->h));
        $this->pdf->SetFont('helvetica', '', 4);
        $this->pdf->SetTextColor(255, 0, 255);
        $this->pdf->Text($rect->x, max(0.0, $rect->y - 0.8), $label);
    }

    private function resolveRole(?string $roleName): FontRole
    {
        return $this->settings->fonts()->roleOrDefault($roleName);
    }

    /** The font size a box actually renders at, in pt. */
    private function effectiveSize(TextBox $box): float
    {
        return $box->size ?? $this->settings->fontSize();
    }

    /** Height of one line in mm, for a size in pt. */
    private function lineHeight(float $sizePt): float
    {
        return Units::ptToMm($sizePt) * $this->settings->cellHeightRatio();
    }

    private function fontAscent(FontRole $role, float $sizePt): float
    {
        return (float) $this->pdf->getFontAscent($role->family, $role->style, $sizePt);
    }

    private function fontDescent(FontRole $role, float $sizePt): float
    {
        return (float) $this->pdf->getFontDescent($role->family, $role->style, $sizePt);
    }

    private function capHeight(FontRole $role, float $sizePt): float
    {
        if (!$this->pdf instanceof MetricsFpdi) {
            throw new ConfigurationException(sprintf(
                'Anchor::CapHeight needs cap-height metrics, which %s does not expose. '
                . 'Have the class returned by PdfSettingsInterface::pdfFactory() extend %s.',
                $this->pdf::class,
                MetricsFpdi::class
            ));
        }

        return $this->pdf->fontCapHeight($role->family, $role->style, $sizePt);
    }

    private function selectFont(?string $roleName, ?float $size): void
    {
        $role = $this->resolveRole($roleName);
        $this->pdf->SetFont($role->family, $role->style, $size ?? $this->settings->fontSize());
    }

    private function applyColor(Color $color): void
    {
        $channels = $color->channels;

        if ($color->model === Color::MODEL_CMYK) {
            $this->pdf->SetTextColorArray([
                'C' => $channels[0],
                'M' => $channels[1],
                'Y' => $channels[2],
                'K' => $channels[3],
            ]);

            return;
        }

        if ($color->model === Color::MODEL_GRAY) {
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
            throw new TemplateNotFoundException(sprintf(
                'Template "%s" was not found in "%s". page() and stamp() take a basename '
                . 'relative to templatePath(), not a full path.',
                $template,
                $this->settings->templatePath()
            ));
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

        $fonts = $this->settings->fonts();
        $role = $fonts->roleOrDefault($box->font);
        $requiredStyle = ($needsBold ? 'B' : '') . ($needsItalic ? 'I' : '');
        $requiredStyle = $requiredStyle === '' ? $role->style : $requiredStyle;

        if ($fonts->supportsStyle($role->family, $requiredStyle)) {
            return;
        }

        $label = match ($requiredStyle) {
            'B' => 'Bold',
            'I' => 'Italic',
            'BI' => 'BoldItalic',
            default => 'Regular',
        };
        $family = $role->logicalFamily !== '' ? $role->logicalFamily : $role->family;
        $suggestion = sprintf('%s-%s.ttf', ucfirst($family), $label);

        throw new MissingFontStyleException(sprintf(
            'Markup in "%s" needs %s for family "%s". Synthetic bold/italic is a no-op for '
            . 'embedded subset fonts. Add %s and register it: ->face(\'%s\', \'%s\', \'%s\') '
            . '(-> key "%s").',
            $box->id,
            $label,
            $family,
            $suggestion,
            $family,
            strtolower($label),
            $suggestion,
            $fonts->cacheKey($role->family, $requiredStyle) ?? \Nadar\PdfGenerator\Support\FontCompiler::keyFor($suggestion)
        ));
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
