# nadar/pdf-generator - invariants

Rules that cannot be inferred from a signature. For how-to guides see [`docs/`](docs/);
for the calibration procedure see [`skills/pdf-template-calibration`](skills/pdf-template-calibration/SKILL.md).

## Geometry

1. Units are **millimetres**, origin **top-left**. Font sizes are **points**. `mm = pt * 25.4 / 72`.
2. `TextBox::y` is the **top of the first line's cell**, not the baseline. With
   `cellHeight = size(mm) * cellHeightRatio`, the first baseline lands at
   `y + (cellHeight - ascent - descent) / 2 + ascent`. Later lines advance by `cellHeight`.
3. `cellHeightRatio` (default 1.25) therefore moves every baseline in the document.
4. Design coordinates are baselines or ink tops. Pass them verbatim with
   `anchor: Anchor::Baseline` or `Anchor::CapHeight` instead of correcting by hand.
5. `probe($box, $text)` returns the resolved box, baseline, line count and post-shrink
   size **without drawing**. `lastMetrics()` returns the same after a write. Use these to
   calibrate in-process; no external PDF tooling is needed.
6. `Anchor::CapHeight` needs `Tcpdf\MetricsFpdi` (the default document class). A custom
   `pdfFactory()` must extend it or that anchor throws.

## Overflow

7. `h` only takes effect through an `Overflow` policy; without one nothing constrains the text.
8. `Overflow::Shrink` **throws** when it cannot fit. `ShrinkThenClip` never throws - use it
   for anything data-driven.
9. Shrinking measures **height**, so wide text wraps before it shrinks. `maxLines: 1` is what
   expresses "one line, whatever size fits"; surplus words are dropped only once the shrink
   floor is reached.
10. `minSize` defaults to 60% of the requested size, not a fixed point value.

## Fonts

11. Faces must be **TrueType-outline** `.ttf`/`.otf` and compiled ahead of rendering.
    OpenType/CFF (`OTTO`) and `.woff`/`.woff2` are rejected with a conversion hint.
12. The compiled cache must exist before the first render: commit it, or build it in CI with
    `vendor/bin/pdf-generator fonts:build`. A trailing separator on `fontCachePath()` is
    optional - the package normalises it, raw TCPDF does not.
13. The font key is derived from the **file name** (`Inter-Bold.ttf` -> `interb`), so two files
    reducing to one key collide; the build rejects that.
14. Weights beyond regular/bold/italic/bolditalic are registered with `face($family, $weight, $file)`
    and get their own TCPDF family internally. Missing weights throw - synthetic bold/italic is a
    silent no-op for embedded subset fonts.
15. `coreFamily()` registers a TCPDF built-in font: no compilation, not embedded, real bold.

## Templates and output

16. `templatePath()` is a **directory**; `page('name.pdf')` and `stamp()` take a basename
    relative to it. The imported box defaults to `CropBox`, which for Canva/InDesign exports
    is often not `MediaBox`.
17. `page($template)` with no explicit format derives page size and orientation from the template.
18. TCPDF writes its LGPL attribution ("Powered by TCPDF") at 1pt into the bottom edge of the
    last page. It is not a defect, and the string is hex-obfuscated in TCPDF's source.
19. `deterministic($timestamp)` pins the timestamps **and** the document id, making output
    byte-stable for golden-file tests and HTTP caching.
20. `bytes()` and `save()` close the document; nothing can be written afterwards.
21. `raw()` is the supported escape hatch for anything unwrapped (one-off shapes, lines).
    Images, QR codes and 1D barcodes have typed APIs - `image()`, `qr()`, `barcode1d()` - and
    do not need it.
22. `K_PATH_CACHE` defaults to the system temp directory. On serverless targets only `/tmp` is
    writable, which that satisfies; never keep compiled font definitions there.
