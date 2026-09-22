<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a cursor-paginated Vultr collection.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly ?string $nextCursor = null,
        private readonly ?string $previousCursor = null,
        private readonly ?int $total = null,
    ) {
    }

    /**
     * Build a page from a decoded Vultr collection response.
     *
     * @param array<string, mixed> $payload
     * @param string               $collectionKey the key holding the records, for example "instances"
     */
    public static function fromPayload(array $payload, string $collectionKey): self
    {
        $items = $payload[$collectionKey] ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        /** @var array<string, mixed> $links */
        $links = is_array($meta['links'] ?? null) ? $meta['links'] : [];

        return new self(
            array_values($items),
            self::cursor($links, 'next'),
            self::cursor($links, 'prev'),
            isset($meta['total']) && is_numeric($meta['total']) ? (int) $meta['total'] : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * The first record on this page, or null when the page is empty.
     *
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return $this->items[0] ?? null;
    }

    /**
     * Total number of records in the collection, as reported by the API.
     */
    public function total(): ?int
    {
        return $this->total;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function previousCursor(): ?string
    {
        return $this->previousCursor;
    }

    public function hasMore(): bool
    {
        return null !== $this->nextCursor;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param array<string, mixed> $links
     */
    private static function cursor(array $links, string $key): ?string
    {
        $value = $links[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
