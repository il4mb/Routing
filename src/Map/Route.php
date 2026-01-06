<?php

namespace Il4mb\Routing\Map;

use Attribute;
use Il4mb\Routing\Callback;
use Il4mb\Routing\Http\Method;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @var string $method
     */
    public string $method;
    /**
     * @var string $path
     */
    public string $path;

    /**
     * @var array<\Il4mb\Routing\Middlewares\Middleware> $middlewares
     */
    public array $middlewares;

    /** Higher wins. */
    public int $priority;

    /** When true, only used if no non-fallback routes match. */
    public bool $fallback;

    /** Optional host/domain constraint (e.g. api.example.com or *.example.com). */
    public ?string $host;

    /** Optional protocol constraint (e.g. http, https, smtp). */
    public ?string $protocol;

    /**
     * Optional header constraints.
     * @var array<string, string|null>
     */
    public array $headers;

    /**
     * Arbitrary metadata for integrations.
     * @var array<string, mixed>
     */
    public array $metadata;
    /**
     * @var array<\Il4mb\Routing\Map\RouteParam> $parameters
     */
    public array $parameters;
    public ?Callback $callback = null;

    /**
     * Route Map constructor
     *
    * @param string $method The HTTP method for this route.
     * @param string $path The path pattern for this route.
     * @param array<class-string<\Il4mb\Routing\Middlewares\Middleware>> $middlewares Middlewares to be applied.
     */
    function __construct(
        string $method,
        string $path,
        array $middlewares = [],
        int $priority = 0,
        bool $fallback = false,
        ?string $host = null,
        ?string $protocol = null,
        array $headers = [],
        array $metadata = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->middlewares = $middlewares;
        $this->priority = $priority;
        $this->fallback = $fallback;
        $this->host = $host;
        $this->protocol = $protocol;
        $this->headers = $headers;
        $this->metadata = $metadata;
        $this->parameters = $this->extractParameters($path);
    }

    /**
     * Extracts parameters from the route path.
     *
     * @param string $path The route path.
     * @return array<RouteParam> The extracted parameters.
     */
    private function extractParameters(string $path): array
    {
        $parameters = [];

        preg_match_all('/\{([^\}]+)\}/', $path, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $raw = trim($match[1]);
            $flag = null;

            // Legacy greedy: {name.*}
            if (str_ends_with($raw, '.*')) {
                $raw = substr($raw, 0, -2);
                $flag = '.*';
            }

            $expected = [];
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\[([^\]]*)\]$/', $raw, $m)) {
                $name = $m[1];
                $expected = array_values(array_filter(array_map('trim', explode(',', $m[2] ?? ''))));
                $parameters[] = new RouteParam($name, $expected, $flag);
                continue;
            }

            // Regex constraint: {name:regex} (including {rest:**}).
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):(.+)$/', $raw, $m)) {
                $name = $m[1];
                $constraint = trim($m[2]);
                $flag = $flag ?? ('regex:' . $constraint);
                $parameters[] = new RouteParam($name, [], $flag);
                continue;
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)$/', $raw, $m)) {
                $parameters[] = new RouteParam($m[1], [], $flag);
            }
        }
        return $parameters;
    }

    /**
     * Returns an array representation of the route for debugging purposes.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            "method" => $this->method,
            "path" => $this->path,
            "callback" => $this->callback,
            "middlewares" => $this->middlewares,
            "priority" => $this->priority,
            "fallback" => $this->fallback,
            "host" => $this->host,
            "protocol" => $this->protocol,
            "headers" => $this->headers,
            "metadata" => $this->metadata,
            "parameters" => $this->parameters
        ];
    }

    /**
     * Clones the route with optional overrides.
     *
     * @param array $args The arguments to override.
     * @return Route The cloned route.
     */
    function clone($args = [])
    {
        $constructorArgs = ["path", "method", "middlewares", "priority", "fallback", "host", "protocol", "headers", "metadata"];
        $newArgs = [];
        foreach ($constructorArgs as $key) {
            if (isset($args[$key])) {
                $newArgs[$key] = $args[$key];
            } else {
                $newArgs[$key] = $this->{$key};
            }
        }
        $route = new Route(...$newArgs);
        foreach ($args as $key => $value) {
            if (in_array($key, $constructorArgs)) continue;
            $route->$key = $value;
        }
        return $route;
    }
}


class RouteParam
{
    /**
     * @var string $name - The name of the parameter.
     */
    public ?string $name = null;
    /**
     * @var string $value - The value of the parameter.
     */
    public ?string $value = null;
    /**
     * @var array<string> $expacted - The expected values for the parameter.
     */
    private array $expacted = [];
    /**
     * @var mixed $flag - The flag associated with the parameter.
     */
    public mixed $flag;
    public function __construct(string $name, array $expacted, mixed $flag)
    {
        $this->name     = $name;
        $this->expacted = array_values(array_filter($expacted ?? []));
        $this->flag   = $flag;
    }

    function hasExpacted(): bool
    {
        return count($this->expacted) > 0;
    }

    function isExpacted($value): bool
    {
        return in_array($value, $this->expacted);
    }

    function __debugInfo()
    {
        return [
            "name" => $this->name,
            "value" => $this->value,
            "expacted" => $this->expacted,
            "flag" => $this->flag
        ];
    }
}
