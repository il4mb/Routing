<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Il4mb\Routing\Engine\DecisionPolicy;
use Il4mb\Routing\Engine\Loaders\PhpRuleLoader;
use Il4mb\Routing\Engine\RouteTable;
use Il4mb\Routing\Engine\RouterEngine;
use Il4mb\Routing\Engine\RoutingContext;
use Il4mb\Routing\Engine\Tracing\ArrayTracer;

// CLI example: long-running worker that can reload routes without process restart.
//
// Run:
//   php examples/hotreload.php
//
// Then edit examples/rules.php and save; the worker will swap RouteTable.

$rulesFile = __DIR__ . '/rules.php';

$tracer = new ArrayTracer();
$engine = new RouterEngine(
    new RouteTable(PhpRuleLoader::load($rulesFile)),
    tracer: $tracer,
    policy: DecisionPolicy::FIRST
);

$lastMtime = @filemtime($rulesFile) ?: 0;

echo "watching {$rulesFile}\n";

echo "try routing: https://docs.example.com/hello\n\n";

while (true) {
    $mtime = @filemtime($rulesFile) ?: 0;
    if ($mtime > $lastMtime) {
        $lastMtime = $mtime;
        $engine->reload(new RouteTable(PhpRuleLoader::load($rulesFile)));
        echo "reloaded rules at " . date('c') . "\n";
    }

    $ctx = new RoutingContext(protocol: 'https', host: 'docs.example.com', path: '/hello', method: 'GET');
    $outcome = $engine->route($ctx);

    if ($outcome->ok && isset($outcome->decision->selected[0])) {
        $selected = $outcome->decision->selected[0];
        echo "selected: {$selected->id} target=" . json_encode($selected->target) . "\n";
    } else {
        echo "no selection\n";
    }

    // Sleep to keep the example simple and predictable.
    usleep(500000);
}
