<?php

namespace Nadar\PdfGenerator;

use Countable;
use IteratorAggregate;
use Nadar\PdfGenerator\Exception\ConfigurationException;
use Traversable;

/**
 * A named collection of {@see TextBox} slots - one page's text geometry.
 *
 * Keeping the geometry in a `Layout` separates *where things go* (measured once
 * off the design) from *what goes there* (per render), which is what
 * {@see PdfGenerator::writeAll()} consumes:
 *
 * ```php
 * $layout = Layout::fromArray([
 *     ['id' => 'title', 'x' => 53.2, 'y' => 58.5, 'w' => 120, 'h' => 11.5, 'font' => 'bold', 'size' => 24],
 *     ['id' => 'meta',  'x' => 53.2, 'y' => 70.1, 'w' => 120, 'h' => 9.5,  'font' => 'bold', 'size' => 19],
 * ]);
 *
 * foreach ($layout->repeat(times: 6, dy: 40.3) as $i => $row) {
 *     $pdf->writeAll($row, $events[$i]);
 * }
 * ```
 *
 * @implements IteratorAggregate<string,TextBox>
 */
final class Layout implements IteratorAggregate, Countable
{
    /** @param array<string,TextBox> $items keyed by slot id */
    public function __construct(private readonly array $items)
    {
    }

    /** Build from boxes, keyed by their own ids. */
    public static function make(TextBox ...$boxes): self
    {
        $items = [];
        foreach ($boxes as $box) {
            $items[$box->id] = $box;
        }

        return new self($items);
    }

    /**
     * Build from plain rows, e.g. a decoded JSON/YAML layout file.
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

    /** Copy with these boxes added, replacing any slot of the same id. */
    public function with(TextBox ...$boxes): self
    {
        $items = $this->items;
        foreach ($boxes as $box) {
            $items[$box->id] = $box;
        }

        return new self($items);
    }

    /** @throws ConfigurationException when no slot has that id */
    public function get(string $id): TextBox
    {
        return $this->items[$id] ?? throw new ConfigurationException(sprintf(
            'Layout has no slot "%s". Known slots: %s.',
            $id,
            $this->items === [] ? '(none)' : implode(', ', array_keys($this->items))
        ));
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

    /** @return array<string,TextBox> */
    public function all(): array
    {
        return $this->items;
    }

    /** Copy with every slot shifted by ($dx, $dy) mm. */
    public function offset(float $dx, float $dy): self
    {
        return new self(array_map(
            static fn (TextBox $box): TextBox => $box->offset($dx, $dy),
            $this->items
        ));
    }

    /**
     * This layout repeated down (or across) the page - one copy per slot.
     *
     * Fixed layouts are almost always "n identical slots"; this expresses the
     * pitch once instead of scattering `$index * $pitch` arithmetic through the
     * render loop. Copy `$i` is offset by `$i * $dy` vertically and
     * `$i * $dx` horizontally, so the first copy is the layout itself.
     *
     * @param int   $times how many copies, at least 1
     * @param float $dy    vertical pitch in mm between consecutive copies
     * @param float $dx    horizontal pitch in mm, for column grids
     *
     * @return list<self> in visual order
     *
     * @throws \InvalidArgumentException when $times is below 1
     */
    public function repeat(int $times, float $dy = 0.0, float $dx = 0.0): array
    {
        if ($times < 1) {
            throw new \InvalidArgumentException(sprintf('Layout::repeat() needs at least 1 repetition, %d given.', $times));
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
}
