# Repeated slots and pagination

Fixed layouts are almost always "n identical slots". Declare one row's geometry
and let `repeat()` produce the rest, instead of scattering `$index * $pitch`
arithmetic through the render loop.

A row is rarely text alone, so a `Layout` holds **any** slot: text, images and
codes together. Declare the row once, and `repeat()` moves all of it:

```php
$row = Layout::fromArray([
    ['id' => 'title', 'x' => 53.2, 'y' => 58.48, 'w' => 120, 'h' => 11.5, 'font' => 'bold', 'size' => 24, 'maxLines' => 1],
    ['id' => 'meta',  'x' => 53.2, 'y' => 70.11, 'w' => 120, 'h' => 9.5,  'font' => 'bold', 'size' => 19, 'maxLines' => 1],
])->with(
    ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85, placeholder: Color::hex('#ff920c')),
    new QrBox('link', x: 179, y: 58.63, size: 19.5, color: Color::hex('#223764')),
);

foreach ($row->repeat(times: 6, dy: 40.3) as $index => $slots) {
    $event = $events[$index];

    $pdf->writeAll($slots, $event);                        // text slots
    $pdf->image($slots->image('photo'), $event['image']);  // image slot
    $pdf->qr($slots->qr('link'), $event['url']);           // code slot
}
```

There is no `$index * $pitch` anywhere: the pitch is stated once, in the
`repeat()` call. `writeAll()` fills the text slots and skips the others, so a
mixed layout can be passed straight to it.

`Layout::fromArray()` builds text slots only - an image or code slot carries
types a flat array cannot express - so add those with `with()`.

The first copy is the layout itself, unshifted; copy `n` is `n * dy` lower. Pass
`dx` instead for a column grid, or both for a matrix.

Derive the pitch from **one slot measured across rows** in the reference, never
by averaging unrelated lines - see
[matching-a-template.md](matching-a-template.md).

## Reaching individual slots

`get()` returns whatever kind of slot lives under an id; the typed accessors
`text()`, `image()`, `qr()` and `barcode()` return that kind or explain what they
found instead:

```php
$slots->text('title');     // TextBox
$slots->image('photo');    // ImageBox
$slots->qr('link');        // QrBox
$slots->barcode('ean');    // BarcodeBox

$slots->texts();           // array<string,TextBox> - what writeAll() fills
$slots->ids();             // every slot id, in declaration order
```

Every slot is immutable and `offset()` returns a copy, so one declaration stays
reusable across rows and pages.

## Pagination

Chunk the data, and start a fresh stamped page per chunk:

```php
foreach (array_chunk($events, self::ROWS_PER_PAGE) as $page) {
    $pdf->page('poster-template.pdf');
    $pdf->write($monthBox, $month);

    foreach ($page as $index => $event) {
        $pdf->writeAll($rows[$index], $event);
    }
}
```

Automatic page breaks are off by design: in a fixed layout a break in the middle
of a row is never what you want. Deciding where pages end is the caller's job,
and `array_chunk()` is usually the whole of it.

For page numbers, chunk first so the total is known:

```php
$pages = array_chunk($events, self::ROWS_PER_PAGE);

foreach ($pages as $number => $page) {
    $pdf->page('template.pdf');
    $pdf->write($folioBox, sprintf('%d / %d', $number + 1, count($pages)));
    // ...
}
```

## Statically checking an array-defined layout

Layouts written as arrays are convenient but easy to typo. The accepted shape is
published as a type alias, so a layout kept in a constant or config file can be
checked by PHPStan:

```php
use Nadar\PdfGenerator\TextBox;

/**
 * @phpstan-import-type TextBoxArray from TextBox
 */
final class PosterLayout
{
    /** @return list<TextBoxArray> */
    public static function row(): array
    {
        return [
            ['id' => 'title', 'x' => 53.2, 'y' => 58.48, 'w' => 120.0, 'h' => 11.5, 'maxLines' => 1],
            ['id' => 'meta',  'x' => 53.2, 'y' => 70.11, 'w' => 120.0, 'h' => 9.5,  'maxLines' => 1],
        ];
    }
}
```

A misspelled key or a wrong value type is then a static error rather than a
surprise at render time.

## Slot ids are data keys

`writeAll()` reads each slot's id from the data array, with `-` and `_`
interchangeable, so `event-title` matches a data key `event_title`. A missing key
writes an empty string rather than throwing - a half-filled document is easier to
debug than an exception halfway through a render. Use `Layout::get()` when you do
want a hard failure on a name you got wrong.

See [`examples/template-overlay-poster.php`](../examples/template-overlay-poster.php)
and [`examples/events-pagination.php`](../examples/events-pagination.php) for both
shapes end to end.
