# nadar/pdf-generator - invariants

Rules that cannot be inferred from a signature. How-to guides are in [`docs/`](docs/); the
calibration procedure is [`skills/pdf-template-calibration`](skills/pdf-template-calibration/SKILL.md).

## Geometry

1. Units are **millimetres**, origin **top-left**. Font sizes are **points**. `mm = pt * 25.4 / 72`.
2. `TextBox::y` is the **top of the first line's cell**, not the baseline. With
   `cellHeight = size(mm) * cellHeightRatio`, the first baseline lands at
   `y + (cellHeight - ascent - descent) / 2 + ascent`; later lines advance by `cellHeight`.
   So `cellHeightRatio` (default 1.25) moves every baseline in the document.
3. Design coordinates are baselines or ink tops: pass them verbatim with
   `anchor: Anchor::Baseline` or `Anchor::CapHeight` rather than correcting by hand.
   `CapHeight` needs `Tcpdf\MetricsFpdi`, so a custom `pdfFactory()` must extend it.
4. `probe($box, $text)` returns the resolved box, baseline, line count and post-shrink size
   **without drawing**; `lastMetrics()` returns the same after a write.

## Layout

5. A `Layout` holds any `Slot` - `TextBox`, `ImageBox`, `QrBox`, `BarcodeBox` - and
   `repeat()` shifts all of them, so a repeated row needs no `$index * $pitch` arithmetic.
6. `writeAll()` fills only text slots and skips the rest; draw those with `image()`, `qr()`
   and `barcode1d()`, reached via `->image($id)`, `->qr($id)`, `->barcode($id)`.
7. `Layout::fromArray()` builds text slots only; add other kinds with `->with(...)`.

## Overflow

8. `h` only acts through an `Overflow` policy, and a policy needs a constraint - `h`,
   `maxLines`, or both. Declaring one with neither throws.
9. `Overflow::Shrink` **throws** when it cannot fit; `ShrinkThenClip` never does - use it for
   anything data-driven.
10. Shrinking measures **height**, so wide text wraps before it shrinks. `maxLines: 1` is
    "one line, whatever size fits", with or without an `h`; surplus words are dropped only at
    the shrink floor - except on an `html` box, which clips instead, since cutting markup
    would break a tag.
11. `minSize` defaults to 60% of the requested size, not a fixed point value.

## Fonts

12. Faces must be **TrueType-outline** `.ttf`/`.otf`, compiled ahead of rendering.
    OpenType/CFF (`OTTO`) and `.woff`/`.woff2` are rejected with a conversion hint.
13. The cache must exist before the first render: commit it, or build it in CI with
    `vendor/bin/pdf-generator fonts:build`. A trailing separator on `fontCachePath()` is
    optional - the package normalises it, raw TCPDF does not.
14. The font key comes from the **file name** (`Inter-Bold.ttf` -> `interb`), so two files
    reducing to one key collide; the build rejects that.
15. Weights beyond regular/bold/italic/bolditalic use `face($family, $weight, $file)` and get
    their own TCPDF family internally. Missing weights throw - synthetic bold/italic is a
    silent no-op for embedded subset fonts. `coreFamily()` registers a TCPDF built-in: no
    compilation, not embedded, real bold.

## Images and codes

16. A missing image draws the box's `placeholder` fill **and then** runs `image()`'s
    `$onMissing`; the two compose. With neither, it throws. `fillShape()` paints a slot's
    outline on its own, for fallbacks needing the shape plus a label.
17. `src/bootstrap.php` sets `QR_FIND_FROM_RANDOM = false`. TCPDF's default picks the QR mask
    from two `rand()`-chosen candidates, so identical input renders differently every time and
    silently defeats `deterministic()`. Doing that means pre-empting TCPDF's whole
    `QRCODEDEFS` block, which `QrCodeDefinitionsTest` guards against drift.

## Templates and output

18. `templatePath()` is a **directory**; `page('name.pdf')` and `stamp()` take a basename
    relative to it. The imported box defaults to `CropBox`, often not `MediaBox` for
    Canva/InDesign exports. `page($template)` with no format derives size and orientation.
19. `assertTemplateSize()` tolerates 0.5mm by default: design tools do not export exact ISO
    sizes (Canva's A4 is 210.079 x 297.127mm). Use `templateSize()` for the real numbers.
20. TCPDF writes its LGPL attribution ("Powered by TCPDF") at 1pt into the bottom edge of the
    last page. Not a defect; the string is hex-obfuscated in TCPDF's source.
21. `deterministic($timestamp)` pins the timestamps **and** the document id, making output
    byte-stable for golden-file tests and HTTP caching.
22. `bytes()` and `save()` close the document; nothing can be written afterwards.
23. `raw()` is the escape hatch for anything unwrapped. Read colours out with
    `Color::toArray()`, which returns TCPDF's channel-array shape.
24. `K_PATH_CACHE` defaults to the system temp directory - fine for serverless, where only
    `/tmp` is writable, but never keep compiled font definitions there.
