<?php

namespace Il4mb\Routing\Engine\Middlewares;

use Il4mb\Routing\Engine\RoutingContext;

final class MiddlewareExecutor
{
    /**
     * @param list<Middleware|callable(RoutingContext, callable):mixed> $middlewares
     * @param callable(RoutingContext):mixed $handler
     * @return mixed
     */
    public static function execute(array $middlewares, RoutingContext $context, callable $handler)
    {
        $next = $handler;

        // Wrap from last -> first for deterministic order.
        for ($i = count($middlewares) - 1; $i >= 0; $i--) {
            $mw = $middlewares[$i];

            $prev = $next;
            $next = function (RoutingContext $ctx) use ($mw, $prev) {
                if ($mw instanceof Middleware) {
                    return $mw->handle($ctx, $prev);
                }

                // Callable middleware: fn(RoutingContext $ctx, callable $next): mixed
                return $mw($ctx, $prev);
            };
        }

        return $next($context);
    }
}
