# Framework integration

A settings class is a plain object, so it is free to use framework helpers. The
one consequence is that it may then not be constructible from a bare CLI
process - which is what the `--fonts=`/`--cache=` options exist for.

## Laravel

```php
namespace App\Pdf;

use Nadar\PdfGenerator\AbstractPdfSettings;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Overflow;
use Nadar\PdfGenerator\Value\Color;

final class BrandPdfSettings extends AbstractPdfSettings
{
    public function fontPath(): string      { return resource_path('pdf/fonts'); }
    public function fontCachePath(): string { return resource_path('pdf/fonts/cache'); }
    public function templatePath(): string  { return resource_path('pdf/templates'); }

    public function fonts(): FontSet
    {
        return FontSet::make()
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
            ->face('inter', 'medium', 'Inter-Medium.ttf')
            ->role('regular', 'inter')
            ->role('bold', 'inter', 'bold')
            ->role('headline', 'inter', 'medium');
    }

    public function textColor(): Color { return Color::hex('#223764'); }

    public function overflow(): Overflow { return Overflow::ShrinkThenClip; }
}
```

Bind it once:

```php
// AppServiceProvider::register()
$this->app->bind(PdfSettingsInterface::class, BrandPdfSettings::class);
```

### Building fonts from artisan

Because `resource_path()` needs a booted application, either pass plain paths to
the CLI:

```bash
vendor/bin/pdf-generator fonts:build --fonts=resources/pdf/fonts --cache=resources/pdf/fonts/cache
```

...or wrap the compiler in a command, which is a handful of lines:

```php
namespace App\Console\Commands;

use App\Pdf\BrandPdfSettings;
use Illuminate\Console\Command;
use Nadar\PdfGenerator\Support\FontCompiler;

final class BuildPdfFonts extends Command
{
    protected $signature = 'pdf:fonts {--force}';
    protected $description = 'Compile the brand fonts into TCPDF definitions';

    public function handle(BrandPdfSettings $settings): int
    {
        $compiled = FontCompiler::compileDirectory(
            $settings->fontPath(),
            $settings->fontCachePath(),
            (bool) $this->option('force'),
            fn (string $notice) => $this->warn($notice),
        );

        foreach ($compiled as $file => $key) {
            $this->line(sprintf('%s -> %s', $file, FontCompiler::cacheFile($settings->fontCachePath(), $key)));
        }

        return self::SUCCESS;
    }
}
```

### Streaming from a controller

```php
public function poster(Month $month, BrandPdfSettings $settings): Response
{
    $pdf = (new PdfGenerator($settings))
        ->title("Highlights {$month->label}")
        // content-derived, so the bytes are stable while the data is
        ->deterministic($month->updated_at->getTimestamp());

    $bytes = (new PosterRenderer($pdf))->render($month);

    return response($bytes, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="highlights.pdf"',
        'ETag' => '"' . md5($bytes) . '"',
    ]);
}
```

`deterministic()` is what makes that `ETag` meaningful: without it every render
produces different bytes and the header changes on every request.

## Symfony

The same class works unchanged; register it as a service and autowire it.

```yaml
# config/services.yaml
services:
    Nadar\PdfGenerator\Contract\PdfSettingsInterface: '@App\Pdf\BrandPdfSettings'
    App\Pdf\BrandPdfSettings: ~
```

```php
#[Route('/poster/{month}.pdf')]
public function poster(Month $month, PdfSettingsInterface $settings): Response
{
    // ...
    return new Response($bytes, 200, ['Content-Type' => 'application/pdf']);
}
```

## Serverless

`K_PATH_CACHE` defaults to the system temp directory, which satisfies targets
where only `/tmp` is writable. Compiled **font definitions** must not live there:
that directory is per-instance and ephemeral, so a cold instance would fail on
its first render. Deploy them with the code.

## Supplying your own TCPDF subclass

`PdfGenerator` is `final`, so `pdfFactory()` is the seam for things only a TCPDF
subclass can do - `Header()`/`Footer()`, custom error handling, or reaching
TCPDF's protected internals:

```php
use Nadar\PdfGenerator\Contract\PdfFactoryInterface;
use Nadar\PdfGenerator\Tcpdf\MetricsFpdi;
use setasign\Fpdi\Tcpdf\Fpdi;

final class LetterheadPdf extends MetricsFpdi
{
    public function Header(): void
    {
        $this->Image(__DIR__ . '/logo.png', 15, 8, 30);
    }
}

final class LetterheadFactory implements PdfFactoryInterface
{
    public function create(string $orientation, string $unit, string|array $format): Fpdi
    {
        return new LetterheadPdf($orientation, $unit, $format);
    }
}

// then, in your settings:
public function pdfFactory(): ?PdfFactoryInterface
{
    return new LetterheadFactory();
}
```

Extend `MetricsFpdi` rather than `Fpdi` directly: it is what exposes cap height,
so `Anchor::CapHeight` keeps working. Headers and footers are off by default
(fixed layouts do not want them); call `$pdf->raw()->setPrintHeader(true)` to
enable yours.

### The "Powered by TCPDF" line

TCPDF writes its LGPL attribution at 1 pt into the bottom edge of the last page
of every document. It is TCPDF's, not this package's, and the string is
hex-obfuscated in TCPDF's source - which is why searching for it finds nothing.
It is essentially invisible in print but present in the text layer, so it will
show up if you diff extracted text.

Suppressing it means overriding TCPDF's protected `$tcpdflink` in a subclass
supplied through `pdfFactory()`. Whether you may is a licensing question, so this
package does not decide it for you.
