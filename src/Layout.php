<?php

namespace Nadar\PdfGenerator;

use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<string,TextBox> */
final readonly class Layout implements IteratorAggregate
{
    /** @param array<string,TextBox> $items */
    public function __construct(private array $items)
    {
    }

    public static function fromArray(array $rows): self
    {
        $items = [];
        foreach ($rows as $row) {
            $box = TextBox::fromArray($row);
            $items[$box->id] = $box;
        }

        return new self($items);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
