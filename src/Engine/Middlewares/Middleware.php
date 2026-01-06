<?php

namespace Il4mb\Routing\Engine\Middlewares;

use Il4mb\Routing\Engine\RoutingContext;

/**
 * Engine-level middleware.
 *
 * This is protocol-agnostic and operates on RoutingContext.
 * The middleware may:
 * - enrich the context (return $next($context->withAttribute(...)))
 * - reject/short-circuit (throw) before reaching the handler
 * - record metrics/tracing (adapter responsibility)
 */
interface Middleware
{
    /**
     * @param callable(RoutingContext):mixed $next
     * @return mixed
     */
    public function handle(RoutingContext $context, callable $next);
}
