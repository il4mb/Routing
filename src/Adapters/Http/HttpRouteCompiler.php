<?php

namespace Il4mb\Routing\Adapters\Http;

use Il4mb\Routing\Engine\Matchers\HeaderMatcher;
use Il4mb\Routing\Engine\Matchers\HostMatcher;
use Il4mb\Routing\Engine\Matchers\MethodMatcher;
use Il4mb\Routing\Engine\Matchers\PathPatternMatcher;
use Il4mb\Routing\Engine\Matchers\ProtocolMatcher;
use Il4mb\Routing\Engine\RouteDefinition;
use Il4mb\Routing\Map\Route as AttributeRoute;

final class HttpRouteCompiler
{
    public static function compile(AttributeRoute $route, string $id): RouteDefinition
    {
        $matchers = [
            new MethodMatcher($route->method),
            new PathPatternMatcher($route->path),
        ];

        if (!empty($route->host)) {
            $matchers[] = new HostMatcher($route->host);
        }

        if (!empty($route->protocol)) {
            $matchers[] = new ProtocolMatcher($route->protocol);
        }

        foreach (($route->headers ?? []) as $name => $value) {
            $matchers[] = new HeaderMatcher((string)$name, $value === null ? null : (string)$value);
        }

        return new RouteDefinition(
            id: $id,
            target: $route,
            matchers: $matchers,
            priority: $route->priority ?? 0,
            fallback: (bool)($route->fallback ?? false),
            condition: null,
            middlewares: $route->middlewares ?? [],
            metadata: $route->metadata ?? [],
        );
    }
}
