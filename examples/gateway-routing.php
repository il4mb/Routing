<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Il4mb\Routing\Engine\DecisionPolicy;
use Il4mb\Routing\Engine\RouteDefinition;
use Il4mb\Routing\Engine\RouteTable;
use Il4mb\Routing\Engine\RouterEngine;
use Il4mb\Routing\Engine\RoutingContext;
use Il4mb\Routing\Engine\Matchers\HostMatcher;
use Il4mb\Routing\Engine\Matchers\HeaderMatcher;
use Il4mb\Routing\Engine\Matchers\PathPatternMatcher;
use Il4mb\Routing\Engine\Matchers\ProtocolMatcher;
use Il4mb\Routing\Engine\Tracing\ArrayTracer;

// Example: gateway-style routing where targets are upstream clusters.
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
    ),
];

$tracer = new ArrayTracer();
$engine = new RouterEngine(
    new RouteTable($routes),
    tracer: $tracer,
    policy: DecisionPolicy::FIRST
);

$ctx = new RoutingContext(
    protocol: 'https',
    host: 'payments.example.com',
    path: '/v1/charge',
    method: 'GET',
    headers: new \Il4mb\Routing\Http\ListPair([
        'x-tenant' => 'eu',
    ]),
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

echo "selected route: {$selected->id}\n";
echo "target: " . json_encode($selected->target) . "\n\n";

echo "trace:\n";
foreach ($tracer->events() as $e) {
    echo "- [{$e['stage']}] {$e['message']}";
    if (!empty($e['data'])) {
        echo " " . json_encode($e['data']);
    }
    echo "\n";
}
