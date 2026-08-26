<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Responses;

use ArrayAccess;
use LogicException;

/**
 * A read-only view of a single NetSuite record. Deliberately generic — field names
 * are whatever NetSuite returned for this record type, not a fixed schema. Not used
 * as a payload builder for create/update; RecordClient takes plain arrays for that.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class NetSuiteRecord implements ArrayAccess
{
    /**
     * @param array<string, mixed> $fields
     * @param array<int, array<string, mixed>> $links
     */
    public function __construct(
        public readonly string $recordType,
        public readonly ?string $id,
        private readonly array $fields,
        public readonly array $links = [],
    ) {
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->fields[$field] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->fields);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->fields[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('NetSuiteRecord is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('NetSuiteRecord is read-only.');
    }
}
