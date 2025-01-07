<?php

namespace Il4mb\Routing\Http;

use ArrayAccess;
use Countable;
use Iterator;

class ListPair implements ArrayAccess, Countable, Iterator
{
    private array $headers;
    private mixed $default;
    private int $key = 0; // Tracks the current iterator position

    public function __construct(array $headers = [], mixed $default = null)
    {
        $this->headers = $headers;
        $this->default = $default;
    }

    // Countable Implementation
    public function count(): int
    {
        return count($this->headers);
    }

    // ArrayAccess Implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->headers[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->headers[$offset] ?? $this->default;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->headers[] = $value;
        } else {
            $this->headers[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->headers[$offset]);
    }

    // Iterator Implementation
    public function current(): mixed
    {
        return $this->headers[array_keys($this->headers)[$this->key]] ?? null;
    }

    public function key(): mixed
    {
        return array_keys($this->headers)[$this->key] ?? null;
    }

    public function next(): void
    {
        $this->key++;
    }

    public function rewind(): void
    {
        $this->key = 0;
    }

    public function valid(): bool
    {
        return $this->key < count($this->headers);
    }

    // Debugging Output
    public function __debugInfo(): array
    {
        return $this->headers;
    }
}
