<?php

namespace Nadar\PdfGenerator;

use Countable;
use IteratorAggregate;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Nadar\PdfGenerator\Exception\InvalidValueException;
use Traversable;

/**
 * A named collection of {@see Slot}s - one page's, or one row's, geometry.
 *
 * Keeping the geometry in a `Layout` separates *where things go* (measured once
 * off the design) from *what goes there* (per render). A layout is not limited
 * to text: an image slot and a code slot belong to the same row, so
 * {@see repeat()} shifts all of them together and the render loop needs no
 * pitch arithmetic at all.
 *
 * ```php
 * $row = Layout::fromArray([
 *     ['id' => 'title', 'x' => 53.2, 'y' => 58.48, 'w' => 120, 'h' => 11.5, 'font' => 'bold', 'size' => 24],
 *     ['id' => 'meta',  'x' => 53.2, 'y' => 70.11, 'w' => 120, 'h' => 9.5,  'font' => 'bold', 'size' => 19],
 * ])->with(
 *     ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85),
 *     new QrBox('link', x: 179, y: 58.63, size: 19.5),
 * );
 *
 * foreach ($row->repeat(times: 6, dy: 40.3) as $index => $slots) {
 *     $event = $events[$index];
 *
 *     $pdf->writeAll($slots, $event);
 *     $pdf->image($slots->image('photo'), $event['image']);
 *     $pdf->qr($slots->qr('link'), $event['url']);
 * }
 * ```
 *
 * @implements IteratorAggregate<string,Slot>
 */
final class Layout implements IteratorAggregate, Countable
{
    /** @param array<string,Slot> $items keyed by slot id */
    public function __construct(private readonly array $items)
    {
    }

    /** Build from slots of any kind, keyed by their own ids. */
    public static function make(Slot ...$slots): self
    {
        $items = [];
        foreach ($slots as $slot) {
            $items[$slot->id] = $slot;
        }

        return new self($items);
    }

    /**
     * Build text slots from plain rows, e.g. a decoded JSON/YAML layout file.
     *
     * Image and code slots carry types that do not survive a flat array well,
     * so add them with {@see with()}.
     *
     * @param iterable<array<string,mixed>> $rows see {@see TextBox::fromArray()} for keys
     */
    public static function fromArray(iterable $rows): self
    {
        $items = [];
        foreach ($rows as $row) {
            $box = TextBox::fromArray($row);
            $items[$box->id] = $box;
        }

        return new self($items);
    }

    /** Copy with these slots added, replacing any slot of the same id. */
    public function with(Slot ...$slots): self
    {
        $items = $this->items;
        foreach ($slots as $slot) {
            $items[$slot->id] = $slot;
        }

        return new self($items);
    }

    /**
     * Any slot, whatever its type.
     *
     * @throws ConfigurationException when no slot has that id
     */
    public function get(string $id): Slot
    {
        return $this->items[$id] ?? throw new ConfigurationException(sprintf(
            'Layout has no slot "%s". Known slots: %s.',
            $id,
            $this->items === [] ? '(none)' : implode(', ', array_keys($this->items))
        ));
    }

    /**
     * A text slot.
     *
     * @throws ConfigurationException when the id is unknown or holds another kind of slot
     */
    public function text(string $id): TextBox
    {
        $slot = $this->get($id);

        return $slot instanceof TextBox ? $slot : throw self::wrongType($id, TextBox::class, $slot);
    }

    /**
     * An image slot.
     *
     * @throws ConfigurationException when the id is unknown or holds another kind of slot
     */
    public function image(string $id): ImageBox
    {
        $slot = $this->get($id);

        return $slot instanceof ImageBox ? $slot : throw self::wrongType($id, ImageBox::class, $slot);
    }

    /**
     * A QR code slot.
     *
     * @throws ConfigurationException when the id is unknown or holds another kind of slot
     */
    public function qr(string $id): QrBox
    {
        $slot = $this->get($id);

        return $slot instanceof QrBox ? $slot : throw self::wrongType($id, QrBox::class, $slot);
    }

    /**
     * A linear barcode slot.
     *
     * @throws ConfigurationException when the id is unknown or holds another kind of slot
     */
    public function barcode(string $id): BarcodeBox
    {
        $slot = $this->get($id);

        return $slot instanceof BarcodeBox ? $slot : throw self::wrongType($id, BarcodeBox::class, $slot);
    }

    /**
     * Only the text slots, which is what {@see PdfGenerator::writeAll()} fills.
     *
     * @return array<string,TextBox>
     */
    public function texts(): array
    {
        return array_filter($this->items, static fn (Slot $slot): bool => $slot instanceof TextBox);
    }

    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->items);
    }

    /** @return array<string,Slot> */
    public function all(): array
    {
        return $this->items;
    }

    /** Copy with every slot shifted by ($dx, $dy) mm. */
    public function offset(float $dx, float $dy): self
    {
        return new self(array_map(
            static fn (Slot $slot): Slot => $slot->offset($dx, $dy),
            $this->items
        ));
    }

    /**
     * This layout repeated down (or across) the page - one copy per slot.
     *
     * Fixed layouts are almost always "n identical slots"; this expresses the
     * pitch once instead of scattering `$index * $pitch` arithmetic through the
     * render loop. Copy `$i` is offset by `$i * $dy` vertically and
     * `$i * $dx` horizontally, so the first copy is the layout itself. Every
     * slot moves, text or not.
     *
     * @param int   $times how many copies, at least 1
     * @param float $dy    vertical pitch in mm between consecutive copies
     * @param float $dx    horizontal pitch in mm, for column grids
     *
     * @return list<self> in visual order
     *
     * @throws InvalidValueException when $times is below 1
     */
    public function repeat(int $times, float $dy = 0.0, float $dx = 0.0): array
    {
        if ($times < 1) {
            throw new InvalidValueException(sprintf('Layout::repeat() needs at least 1 repetition, %d given.', $times));
        }

        $copies = [];
        for ($i = 0; $i < $times; ++$i) {
            $copies[] = $i === 0 ? $this : $this->offset($i * $dx, $i * $dy);
        }

        return $copies;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    /** @param class-string $expected */
    private static function wrongType(string $id, string $expected, Slot $actual): ConfigurationException
    {
        return new ConfigurationException(sprintf(
            'Layout slot "%s" is a %s, not a %s.',
            $id,
            self::shortName($actual::class),
            self::shortName($expected)
        ));
    }

    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
