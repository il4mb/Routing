<?php

namespace Il4mb\Routing\Map;

use Attribute;
use Il4mb\Routing\Callback;
use Il4mb\Routing\Http\Method;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @var Method $method
     */
    public readonly Method $method;
    /**
     * @var string $path
     */
    public readonly string $path;

    /**
     * @var array<\Il4mb\Routing\Middlewares\Middleware> $middlewares
     */
    public readonly array $middlewares;
    /**
     * @var array<\Il4mb\Routing\Map\RouteParam> $parameters
     */
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
        preg_match_all('/\{(\w+)(?:\[([^\]]+)\])?(|.)\}/', $path, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = $match[1];
            $expected = explode(',', $match[2] ?? "") ?? [];
            $flag = $match[3] ?? null;
            $parameters[] = new RouteParam($name, $expected, $flag);
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
    public function __construct(string $name, array $expacted = [], mixed $flag)
    {
        $this->name     = $name;
        $this->expacted = $expacted;
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
