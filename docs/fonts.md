# Fonts

TCPDF cannot embed a `.ttf` directly. Every face has to be converted into a PHP
font definition once, ahead of rendering. Getting this wrong is the most common
way to lose an afternoon, so the rules are short and strict.

## Formats

| Format | Works | Notes |
| --- | --- | --- |
| `.ttf` | yes | the safe choice |
| `.otf` with TrueType outlines | yes | detected automatically |
| `.otf` with CFF outlines (`OTTO`) | **no** | TCPDF cannot embed it; use the TrueType build |
| `.woff`, `.woff2` | **no** | web wrappers; convert first |
| `.ttc` (collections) | **no** | extract the individual faces |

Brand kits routinely ship only web fonts. Convert with fontTools:

```bash
pip install fonttools
python3 -c "from fontTools.ttLib import TTFont; f=TTFont('Brand-Regular.woff2'); f.flavor=None; f.save('Brand-Regular.ttf')"
```

`fonts:build` warns per skipped web font and fails outright on a directory with
nothing usable, rather than exiting 0 and letting the failure surface later as a
missing cache.

## Building the cache

```bash
# from a settings class (needs it to be constructible outside a framework)
vendor/bin/pdf-generator fonts:build --settings="App\Pdf\BrandPdfSettings"

# or from plain paths, which always works
vendor/bin/pdf-generator fonts:build --fonts=resources/pdf/fonts --cache=resources/pdf/fonts/cache

# verify every declared face has a definition
vendor/bin/pdf-generator fonts:check --settings="App\Pdf\BrandPdfSettings"
```

The build prints the **absolute** path of every file it writes. If that is not
where you expected, the paths are wrong - which is the whole point of printing them.

Two things worth knowing:

- **The cache must exist before the first render.** Commit it, or build it in CI.
  There is no lazy compilation.
- **TCPDF skips faces that already have a definition**, so a stale cache is never
  refreshed. Pass `--force` after replacing a font file.

## Keys are derived from file names

The definition is named after the source file: lowercase it, drop everything
outside `[a-z0-9_]`, then replace `bold`/`oblique`/`italic`/`regular` with
`b`/`i`/`i`/`''`.

| File | Key |
| --- | --- |
| `Inter-Regular.ttf` | `inter` |
| `Inter-Bold.ttf` | `interb` |
| `Inter-BoldItalic.ttf` | `interbi` |
| `Brother-1816-Medium.ttf` | `brother1816medium` |

So `Inter.ttf` and `Inter-Regular.ttf` reduce to the same key and would
overwrite each other. The build detects that and names both files.

## Declaring faces

TCPDF models only four styles per family. Use `family()` for those:

```php
FontSet::make()
    ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf', 'Inter-Italic.ttf', 'Inter-BoldItalic.ttf')
    ->role('regular', 'inter')
    ->role('bold', 'inter', 'bold');
```

A four-weight brand family - Regular/Medium/Bold/ExtraBold, very common - uses
`face()`. Weights outside the canonical four get their own TCPDF family behind
the scenes, so one logical family still covers them all:

```php
FontSet::make()
    ->face('brother', 'regular', 'Brother-1816-Regular.ttf')
    ->face('brother', 'medium', 'Brother-1816-Medium.ttf')
    ->face('brother', 'bold', 'Brother-1816-Bold.ttf')
    ->face('brother', 'extrabold', 'Brother-1816-ExtraBold.ttf')
    ->role('body', 'brother')
    ->role('lead', 'brother', 'medium')
    ->role('title', 'brother', 'bold');
```

**Roles**, not families, are what layouts refer to (`TextBox::$font`). That keeps
the layout talking about `headline` and `meta` rather than about font files, so
swapping the brand font touches one method.

## Why missing weights throw

Asking TCPDF for a style with no registered face is a **silent no-op** for
embedded subset fonts: the text renders in the regular weight and looks correct
in code review, wrong in print. So `role()` fails at configuration time and
names the file to add. The same guard applies to `<b>`/`<i>` markup in an
`html: true` box.

## Core fonts

For scripts, tests and examples, `coreFamily()` registers one of TCPDF's
built-ins - no compilation, no binaries, and a real bold face:

```php
FontSet::make()
    ->coreFamily('helvetica')
    ->role('regular', 'helvetica')
    ->role('bold', 'helvetica', 'bold');
```

Core fonts are metrically standard rather than brand-accurate, so production
print work should use real files.

## CI

Either commit the cache directory, or rebuild it:

```yaml
- run: vendor/bin/pdf-generator fonts:build --fonts=resources/pdf/fonts --cache=resources/pdf/fonts/cache
- run: vendor/bin/pdf-generator fonts:check --settings="App\Pdf\BrandPdfSettings"
```

Do **not** point `fontCachePath()` at the system temp directory on serverless
targets: it is per-instance and ephemeral, so the first request on a cold
instance would fail.
