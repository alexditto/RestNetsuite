<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Responses;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * A page of results from a record list or SuiteQL query endpoint. Items are left as
 * raw associative arrays rather than NetSuiteRecord instances: record-list items are
 * typically just {id, links} refs (not full field data), and SuiteQL rows are
 * arbitrary column selections with no fixed "record" shape — wrapping either in
 * NetSuiteRecord would imply field data that isn't actually there.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class NetSuiteCollection implements IteratorAggregate, Countable
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $links
     */
    public function __construct(
        public readonly array $items,
        public readonly int $count,
        public readonly bool $hasMore,
        public readonly int $offset,
        public readonly int $totalResults,
        public readonly array $links = [],
    ) {
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
