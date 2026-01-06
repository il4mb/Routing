<?php

use Il4mb\Routing\Engine\RouteDefinition;
use Il4mb\Routing\Engine\Matchers\HostMatcher;
use Il4mb\Routing\Engine\Matchers\PathPatternMatcher;
use Il4mb\Routing\Engine\Matchers\ProtocolMatcher;

// This file is intentionally pure PHP "configuration as code".
// It must return list<RouteDefinition> (or a callable returning that list).

return [
    new RouteDefinition(
        id: 'docs.site',
        target: ['cluster' => 'static-docs'],
        matchers: [
            new ProtocolMatcher('https'),
            new HostMatcher('docs.example.com'),
            new PathPatternMatcher('/**'),
        ],
        priority: 50,
    ),

    // Fallback
    new RouteDefinition(
        id: 'default.fallback',
        target: ['cluster' => 'default'],
        matchers: [
            new PathPatternMatcher('/**'),
        ],
        fallback: true,
    ),
];
