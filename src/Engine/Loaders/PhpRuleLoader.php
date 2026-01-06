<?php

namespace Il4mb\Routing\Engine\Loaders;

use Il4mb\Routing\Engine\Errors\InvalidRouteDefinitionException;
use Il4mb\Routing\Engine\RouteDefinition;

final class PhpRuleLoader
{
    /**
     * Loads routes from a PHP file.
     *
     * The file must return one of:
     * - list<RouteDefinition>
     * - callable(): list<RouteDefinition>
     */
    public static function load(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new InvalidRouteDefinitionException("Rules file not found: {$filePath}");
        }

        /** @var mixed $val */
        $val = require $filePath;

        if (is_callable($val)) {
            $val = $val();
        }

        if (!is_array($val)) {
            throw new InvalidRouteDefinitionException('Rules file must return an array of RouteDefinition');
        }

        foreach ($val as $r) {
            if (!$r instanceof RouteDefinition) {
                throw new InvalidRouteDefinitionException('Rules array must contain only RouteDefinition instances');
            }
        }

        /** @var list<RouteDefinition> $val */
        return array_values($val);
    }
}
