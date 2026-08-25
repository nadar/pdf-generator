# Images and shapes

Every template overlay eventually needs "fit this photo into that box, cropped
to that outline". That is `image()`.

```php
$pdf->image(
    ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85),
    $event['image'],
);
```

`ImageBox::circle()` takes the centre and diameter, which is how a design
describes a round photo - no `cx - diameter / 2` conversion to get wrong.

## Fit

| `Fit` | Behaviour |
| --- | --- |
| `Cover` (default) | fills the box, crops the overflowing axis, keeps the ratio |
| `Contain` | fits entirely inside, leaves space, keeps the ratio |
| `Stretch` | fills the box exactly, **does not** keep the ratio |

`ImageBox::placement($pixelWidth, $pixelHeight)` returns the `[x, y, w, h]` the
image will be drawn at, which is useful in tests and calibration scripts.

## Shape

```php
new ImageBox('hero', x: 20, y: 20, w: 60, h: 40);                        // rectangle
ImageBox::circle('avatar', cx: 35, cy: 35, diameter: 30);                // ellipse in the box
ImageBox::rounded('card', 20, 20, 60, 40, radius: 4.0);                  // rounded corners
new ImageBox('r', 20, 20, 60, 40, Fit::Cover, Shape::roundRect(4.0));    // the long form
```

The clip path is emitted for you. There is no need to know that `'CNZ'` is
TCPDF's nonzero-winding clip operator, or to count to the fifteenth argument of
`Image()`.

## Missing images

Remote images fail in production. Three ways to handle it, in the order they
apply:

```php
// 1. a callback, for full control over the fallback
$pdf->image($box, $url, fn (ImageBox $box) => $this->drawInitials($box));

// 2. a placeholder colour, drawn in the box's own shape
$pdf->image(ImageBox::circle('photo', cx: 30, cy: 68, diameter: 31, placeholder: Color::hex('#ff920c')), $url);

// 3. nothing - a MissingImageException naming the box and the reason
$pdf->image($box, $url);
```

Prefer a designed fallback over an empty gap: if the reference PDF shows a
coloured circle where an image is missing, that *is* the specification.

## One fetch per source

Reading an image's dimensions and embedding it are two separate needs, and
serving both naively downloads a remote image twice. `image()` resolves each
distinct source once per document and hands the bytes to TCPDF from memory.
Failures are remembered too, so a placeholder row does not retry a dead URL.

```php
$pdf->imageLoader()->cached();   // how many sources are buffered
$pdf->imageLoader()->clear();    // release them
```

Local paths are not buffered - the dimensions are read from the file and TCPDF
reads it again from disk, which keeps memory flat for large documents.

## Resolution

`dpi:` (default 300) controls the resolution the image is embedded at. Lower it
to shrink the file when the output is screen-only.

## Shapes without an image

Plain vector drawing is not wrapped, because there is no typed shape to hang it
on - `raw()` is the supported route:

```php
$raw = $pdf->raw();
$raw->SetFillColorArray(Color::hex('#147fbb')->toArray());
$raw->Rect(0, 245, 210, 52, 'F');
$raw->Ellipse(150, 245, 90, 22, 0, 0, 360, 'F');
$raw->Line(18, 22, 192, 22);
```

`Color::toArray()` returns the channel list in exactly the shape TCPDF's
`Set*ColorArray()` expects, for all three colour models.
