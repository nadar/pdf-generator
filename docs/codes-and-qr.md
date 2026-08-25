# QR codes and barcodes

```php
$pdf->qr(new QrBox('link', x: 179, y: 58.6, size: 19.5, color: Color::hex('#223764')), $event['url']);
```

A code is a **slot**: where it goes and how it looks, declared separately from
what it encodes. That is what lets it live in the same `Layout` as the
row's text and image, so one `repeat()` moves the whole row - see
[repeated-slots-and-pagination.md](repeated-slots-and-pagination.md).

For a one-off code that no layout owns, `qrAt()` takes the coordinates directly:

```php
$pdf->qrAt($url, x: 179, y: 58.6, size: 19.5, color: Color::hex('#223764'));
```

The defaults are the ones a designed template wants, and each of them matters
for print.

## Why the defaults are what they are

**Transparent background.** This is what makes a code work *on* artwork. If the
bottom third of your poster is a solid brand-blue wave, a white QR tile punches
a hole through the design. With no background the code sits directly on the
wave and still scans, because scanners need **contrast, not white**. That is the
difference between "pasted on" and "designed in".

**No quiet zone.** TCPDF's automatic padding is four modules, added *inside* the
box you specified - so the rendered code no longer matches the size you measured
off the design. With `quietZone: 0` the code fills the box exactly and the
design's own whitespace serves as the quiet zone.

**Brand-coloured modules.** Pass any `Color`. Dark-on-mid-tone scans fine and
keeps the sheet on-palette; black is only the default because it is always safe.

**The cursor is preserved.** TCPDF's barcode writers move its internal cursor,
which then interferes with the next absolutely-positioned write. `qr()` and
`barcode1d()` restore it, so a code can never shift neighbouring text.

## Reproducible output

TCPDF picks the QR mask pattern by scoring candidates - but at its default
`QR_FIND_FROM_RANDOM = 2` it throws away six of the eight candidates using
`rand()`. The same payload therefore renders a *different* pattern on every
call, which silently defeats [`deterministic()`](testing-generated-pdfs.md): a
document with a code on it never produces stable bytes, so golden-file tests
and `ETag`s based on it are worthless.

`src/bootstrap.php` sets that constant to `false`. All eight masks are scored,
so output is reproducible **and** the chosen mask is the genuinely best one
rather than the best of two random candidates. The cost is four times the mask
evaluation per code, which is not measurable next to rendering a page.

Doing this means pre-empting TCPDF's entire `QRCODEDEFS` block - defining the
one constant alone still lets TCPDF's block run, which emits a redefinition
warning on the first render. `QrCodeDefinitionsTest` parses the installed TCPDF
source and fails if that copy ever drifts, so a TCPDF upgrade cannot quietly
break it.

## Error correction

```php
$pdf->qr(new QrBox('link', x: 179, y: 58.6, size: 19.5, level: EccLevel::H), $url);
```

| Level | Recovery | When |
| --- | --- | --- |
| `L` | ~7% | small payload, unobstructed |
| `M` | ~15% | **the default.** The right trade-off around 20 mm with a full URL |
| `Q` | ~25% | |
| `H` | ~30% | needed when a logo overlaps the code |

Higher levels pack more modules into the same box. At ~20 mm with a full URL,
`H` visibly thickens the grid and starts to matter for scanning; `M` stays
comfortable.

## A quiet zone when you do want one

`quietZone` counts **barcode modules**, matching TCPDF (whose "auto" is 4):

```php
$pdf->qr(new QrBox('link', x: 20, y: 20, size: 25, quietZone: 4, background: Color::white()), $url);
```

## Linear barcodes

For invoices and logistics:

```php
$pdf->barcode1d(
    new BarcodeBox('ean', Barcode1D::Ean13, x: 20, y: 250, w: 50, h: 15, showText: true),
    '4006381333931',
);

// or, without declaring a slot:
$pdf->barcode1dAt('4006381333931', Barcode1D::Ean13, x: 20, y: 250, w: 50, h: 15, showText: true);
```

`Barcode1D` covers Code 128 (and its subsets), Code 39/93, Interleaved 2 of 5,
EAN-8/13, UPC-A/E and Postnet. Note that `padding` here is in **millimetres**,
unlike `QrBox::$quietZone`'s module count - that difference is TCPDF's, and it
is the kind of thing worth checking rather than assuming.

## Keeping the sheet live

Generating the code per row from the record's own URL is what keeps a printed
sheet useful: the poster in the window links back to the actual detail page.
Prefer a canonical URL over a redirect - a shortener adds a hop and a dependency
for something that will be on paper for a month.
