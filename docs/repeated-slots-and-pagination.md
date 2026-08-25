# Repeated slots and pagination

Fixed layouts are almost always "n identical slots". Declare one row's geometry
and let `repeat()` produce the rest, instead of scattering `$index * $pitch`
arithmetic through the render loop.

```php
$row = Layout::fromArray([
    ['id' => 'title', 'x' => 53.2, 'y' => 58.48, 'w' => 120, 'h' => 11.5, 'font' => 'bold', 'size' => 24, 'maxLines' => 1],
    ['id' => 'meta',  'x' => 53.2, 'y' => 70.11, 'w' => 120, 'h' => 9.5,  'font' => 'bold', 'size' => 19, 'maxLines' => 1],
]);

$rows = $row->repeat(times: 6, dy: 40.3);

foreach ($events as $index => $event) {
    $pdf->writeAll($rows[$index], $event);
}
```

The first copy is the layout itself, unshifted; copy `n` is `n * dy` lower. Pass
`dx` instead for a column grid, or both for a matrix.

Derive the pitch from **one slot measured across rows** in the reference, never
by averaging unrelated lines - see
[matching-a-template.md](matching-a-template.md).

## Non-text elements

`repeat()` covers text. Images and codes take the offset directly, which keeps
the row's arithmetic in one place:

```php
foreach ($page as $index => $event) {
    $offset = $index * self::ROW_PITCH;

    $pdf->writeAll($rows[$index], $event);
    $pdf->image($photoSlot->offset(0, $offset), $event['image']);
    $pdf->qr($event['url'], x: 179, y: 58.63 + $offset, size: 19.5);
}
```

`ImageBox::offset()` and `TextBox::offset()` both return copies, so the
originals stay reusable across pages.

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

## Slot ids are data keys

`writeAll()` reads each slot's id from the data array, with `-` and `_`
interchangeable, so `event-title` matches a data key `event_title`. A missing key
writes an empty string rather than throwing - a half-filled document is easier to
debug than an exception halfway through a render. Use `Layout::get()` when you do
want a hard failure on a name you got wrong.

See [`examples/template-overlay-poster.php`](../examples/template-overlay-poster.php)
and [`examples/events-pagination.php`](../examples/events-pagination.php) for both
shapes end to end.
