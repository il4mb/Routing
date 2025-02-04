<?php

namespace Il4mb\Routing\Map;

use Attribute;
use Il4mb\Routing\Callback;
use Il4mb\Routing\Http\Method;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public readonly Method $method;
    public readonly string $path;
    public readonly array $middlewares;
    public readonly array $parameters;
    public readonly ?Callback $callback;

    /**
     * Route Map constructor
     *
     * @param Method $method The HTTP method for this route.
     * @param string $path The path pattern for this route.
     * @param array<class-string<\Il4mb\Routing\Middlewares\Middleware>> $middlewares Middlewares to be applied.
     */
    function __construct(
        Method $method,
        string $path,
        array $middlewares = []
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->middlewares = $middlewares;
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
        preg_match_all('/\{(\w+)(?:\[([^\]]+)\])?\}/', $path, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1];
            $expected = isset($match[2]) ? explode(',', $match[2]) : [];
            $parameters[] = new RouteParam($name, $expected);
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
            "method" => $this->method->name,
            "path" => $this->path,
            "callback" => $this->callback,
            "middlewares" => $this->middlewares,
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
        $constructorArgs = ["path", "method", "middlewares"];
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
    public readonly string $name;
    private readonly array $expacted;
    public function __construct(string $name, array $expacted = [])
    {
        $this->name  = $name;
        $this->expacted = $expacted;
    }

    function hasExpacted(): bool
    {
        return count($this->expacted) > 0;
    }

    function isExpacted($value): bool
    {
        return in_array($value, $this->expacted);
    }
}
