<?php

namespace Il4mb\Routing\Http;

use ArrayAccess;
use Countable;
use Iterator;

class ListPair implements ArrayAccess, Countable, Iterator
{
    private array $array;
    private mixed $default;
    private int $key = 0; // Tracks the current iterator position

    public function __construct(array $array = [], mixed $default = null)
    {
        $keys   = array_map("strtolower", array_keys($array));
        $values = array_values($array);
        $this->array = array_combine($keys, $values);
        $this->default = $default;
    }

    // Countable Implementation
    public function count(): int
    {
        return count($this->array);
    }

    // ArrayAccess Implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->array[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->array[strtolower($offset)] ?? $this->default;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->array[] = $value;
        } else {
            $this->array[strtolower($offset)] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->array[$offset]);
    }

    // Iterator Implementation
    public function current(): mixed
    {
        return $this->array[array_keys($this->array)[$this->key]] ?? null;
    }

    public function key(): mixed
    {
        return array_keys($this->array)[$this->key] ?? null;
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
        return $this->key < count($this->array);
    }

    // Debugging Output
    public function __debugInfo(): array
    {
        return $this->array;
    }
}
