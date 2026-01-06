<?php

namespace Il4mb\Routing\Engine;

use Il4mb\Routing\Http\ListPair;

/**
 * Immutable routing input.
 *
 * This is intentionally protocol-agnostic: HTTP, SMTP, TCP proxying, etc.
 * Adapters may populate fields from environment-specific requests.
 */
final class RoutingContext
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $protocol,
        public string $host,
        public string $path,
        public ?string $method = null,
        public ?ListPair $headers = null,
        public array $attributes = [],
    ) {
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;
        return new self(
            protocol: $this->protocol,
            host: $this->host,
            path: $this->path,
            method: $this->method,
            headers: $this->headers,
            attributes: $attributes,
        );
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
