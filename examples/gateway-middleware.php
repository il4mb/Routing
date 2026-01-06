<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Il4mb\Routing\Engine\DecisionPolicy;
use Il4mb\Routing\Engine\RouteDefinition;
use Il4mb\Routing\Engine\RouteTable;
use Il4mb\Routing\Engine\RouterEngine;
use Il4mb\Routing\Engine\RoutingContext;
use Il4mb\Routing\Engine\Matchers\HeaderMatcher;
use Il4mb\Routing\Engine\Matchers\HostMatcher;
use Il4mb\Routing\Engine\Matchers\PathPatternMatcher;
use Il4mb\Routing\Engine\Matchers\ProtocolMatcher;
use Il4mb\Routing\Engine\Middlewares\Middleware;
use Il4mb\Routing\Engine\Middlewares\MiddlewareExecutor;
use Il4mb\Routing\Http\ListPair;

// Example: middleware pipeline wrapped around "execute selected target".
//
// This is how gateways/proxies typically work:
// 1) route -> select a target
// 2) run policy middlewares (auth, tenant, rate limit, logging)
// 3) forward to upstream / return decision

final class RequireTenantHeader implements Middleware
{
    public function handle(RoutingContext $context, callable $next)
    {
        $headers = $context->headers;
        $tenant = $headers ? $headers['x-tenant'] : null;

        if ($tenant === null || $tenant === '') {
            throw new RuntimeException('missing required header: x-tenant');
        }

        return $next($context->withAttribute('tenant', (string)$tenant));
    }
}

final class AddRequestId implements Middleware
{
    public function handle(RoutingContext $context, callable $next)
    {
        $rid = bin2hex(random_bytes(8));
        return $next($context->withAttribute('request_id', $rid));
    }
}

$routes = [
    new RouteDefinition(
        id: 'payments.eu.v1',
        target: ['cluster' => 'payments-eu', 'timeout_ms' => 1500],
        matchers: [
            new ProtocolMatcher('https'),
            new HostMatcher('payments.example.com'),
            new PathPatternMatcher('/v1/**'),
            new HeaderMatcher('x-tenant', 'eu'),
        ],
        priority: 100,
        // Route-specific policy
        middlewares: [
            new RequireTenantHeader(),
        ]
    ),

    new RouteDefinition(
        id: 'payments.default',
        target: ['cluster' => 'payments-global', 'timeout_ms' => 2000],
        matchers: [
            new ProtocolMatcher('https'),
            new HostMatcher('payments.example.com'),
            new PathPatternMatcher('/**'),
        ],
        priority: 10,
        fallback: true,
        middlewares: [
            new RequireTenantHeader(),
        ]
    ),
];

$engine = new RouterEngine(new RouteTable($routes), policy: DecisionPolicy::FIRST);

$ctx = new RoutingContext(
    protocol: 'https',
    host: 'payments.example.com',
    path: '/v1/charge',
    method: 'GET',
    headers: new ListPair([
        'x-tenant' => 'eu',
    ])
);

$outcome = $engine->route($ctx);
if (!$outcome->ok) {
    fwrite(STDERR, "routing error: " . $outcome->error->getMessage() . PHP_EOL);
    exit(1);
}

$selected = $outcome->decision->selected[0] ?? null;
if ($selected === null) {
    fwrite(STDERR, "no selection\n");
    exit(2);
}

// Global middlewares run for all requests; route middlewares run for that target.
$globalMiddlewares = [
    new AddRequestId(),
];

$allMiddlewares = array_merge($globalMiddlewares, $selected->middlewares);

$result = MiddlewareExecutor::execute(
    $allMiddlewares,
    $ctx,
    function (RoutingContext $finalCtx) use ($selected) {
        // This is your adapter "handler" step.
        // In a real gateway, this would forward to $selected->target['cluster'].
        return [
            'selected_route' => $selected->id,
            'target' => $selected->target,
            'tenant' => $finalCtx->getAttribute('tenant'),
            'request_id' => $finalCtx->getAttribute('request_id'),
        ];
    }
);

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
