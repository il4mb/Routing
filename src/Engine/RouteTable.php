<?php

namespace Il4mb\Routing\Engine;

use Il4mb\Routing\Engine\Errors\InvalidRouteDefinitionException;
use Il4mb\Routing\Engine\Matchers\HostMatcher;
use Il4mb\Routing\Engine\Matchers\MethodMatcher;

/**
 * Immutable route table with lightweight indexing.
 */
final class RouteTable
{
    /**
     * @var list<RouteDefinition>
     */
    private array $routes;

    /**
     * @var array<string, list<int>> Bucket key => list of route indexes
     */
    private array $index = [];

    /**
     * @param list<RouteDefinition> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = array_values($routes);

        foreach ($this->routes as $i => $route) {
            if ($route->id === '') {
                throw new InvalidRouteDefinitionException('Route id cannot be empty');
            }

            // Basic key: method|host. Empty means wildcard.
            $method = '';
            $host = '';

            foreach ($route->matchers as $matcher) {
                if ($matcher instanceof MethodMatcher) {
                    $method = $matcher->method();
                }
                if ($matcher instanceof HostMatcher) {
                    $host = $matcher->hostPattern();
                }
            }

            $key = strtoupper($method) . '|' . strtolower($host);
            $this->index[$key] ??= [];
            $this->index[$key][] = $i;

            // Wildcard method/host buckets to widen candidate selection.
            $fallbackKeys = [
                strtoupper($method) . '|',
                '|' . strtolower($host),
                '|',
            ];
            foreach ($fallbackKeys as $k) {
                $this->index[$k] ??= [];
                $this->index[$k][] = $i;
            }
        }
    }

    /**
     * @return list<RouteDefinition>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @return list<RouteDefinition>
     */
    public function candidates(string $method = '', string $host = ''): array
    {
        $key = strtoupper($method) . '|' . strtolower($host);
        $indexes = $this->index[$key] ?? ($this->index['|'] ?? []);

        $unique = [];
        foreach ($indexes as $idx) {
            $unique[$idx] = true;
        }

        $out = [];
        foreach (array_keys($unique) as $idx) {
            $out[] = $this->routes[$idx];
        }

        return $out;
    }
}
