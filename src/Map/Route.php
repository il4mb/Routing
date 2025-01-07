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

    /**
     * Route Map constructor
     *
     * @param \Il4mb\Routing\Http\Method $method
     * @param string $path
     * @param array<class-string<\Il4mb\Routing\Middlewares\Middleware>> $middlewares
     * @param \Il4mb\Routing\Callback|null $callback
     */
    function __construct(
        Method $method,
        string $path,
        array $middlewares = []
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->middlewares = $middlewares;

        $parameters = [];
        preg_match_all("/(\{(.*?)\})/", $path, $mathes);
        if (isset($mathes[2])) {
            foreach ($mathes[2] as $math) {
                $expacted = [];
                preg_match("/(\w+)(\[(.*?)\])/", $math, $mathes);
                if (isset($mathes[1], $mathes[3])) {
                    $name     = $mathes[1];
                    $expacted = explode(",", $mathes[3]);
                } else {
                    $name = $math;
                }
                $parameters[] = new RouteParam($name, $expacted);
            }
        }
        $this->parameters = $parameters;
    }


    public readonly ?Callback $callback;

    function __debugInfo()
    {
        return [
            "method" => $this->method->name,
            "path"   => $this->path,
            "callback"    => $this->callback,
            "middlewares" => $this->middlewares,
            "parameters"  => $this->parameters
        ];
    }
}


class RouteParam
{
    public readonly string $name;
    private readonly array $expacted;
    public readonly string|null $value;
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
