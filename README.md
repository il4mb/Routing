# Routing Engine (PHP)

This repository provides a deterministic routing engine that can be embedded into infrastructure-style software:

- HTTP applications (controller routing)
- API gateways and reverse proxies (upstream selection)
- Programmable network services (policy routing)
- Mail servers (message classification and delivery policy)

The project contains both:

- a **protocol-agnostic core** (`src/Engine/*`) with explicit match and decision phases, and
- a **legacy HTTP router** (`src/Router.php`) that compiles attribute routes into the core engine.

The intent is to keep routing decisions explainable, testable, and observable.

## Requirements

- PHP 8.1+
- Composer (for autoloading)

## Installation

```bash
composer require il4mb/routing
```

## Quick Start (HTTP Attribute Routes)

```php
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Router;

final class AdminController
{
    #[Route(Method::GET, '/admin/home', priority: 50)]
    public function home()
    {
        return 'ok';
    }

    // Fallback route (only used if nothing else matches)
    #[Route(Method::GET, '/{path.*}', fallback: true)]
    public function notFound(string $path, Request $req, Response $res, callable $next)
    {
        return ['error' => 'not_found', 'path' => $path];
    }
}

$router = new Router(options: [
    // Production deployments typically disable this side-effect.
    'manageHtaccess' => false,

    // chain|first|error_on_ambiguous
    'decisionPolicy' => 'first',

    // Enable only while debugging.
    'debugTrace' => true,
]);

$router->addRoute(new AdminController());

$response = $router->dispatch(new Request());
echo $response->send();
```

When `debugTrace=true`, the router stores trace data into:

- `Request::get('__route_trace')`
- `Request::get('__route_decision')`

## Using the Core Engine (Protocol-Agnostic)

The engine routes a `RoutingContext` through a deterministic pipeline.

```php
use Il4mb\Routing\Engine\DecisionPolicy;
use Il4mb\Routing\Engine\RouteDefinition;
use Il4mb\Routing\Engine\RouteTable;
use Il4mb\Routing\Engine\RouterEngine;
use Il4mb\Routing\Engine\RoutingContext;
use Il4mb\Routing\Engine\Matchers\HostMatcher;
use Il4mb\Routing\Engine\Matchers\PathPatternMatcher;
use Il4mb\Routing\Engine\Matchers\ProtocolMatcher;

$routes = [
    new RouteDefinition(
        id: 'proxy.payments.eu',
        target: ['cluster' => 'payments-eu'],
        matchers: [
            new ProtocolMatcher('https'),
            new HostMatcher('payments.example.com'),
            new PathPatternMatcher('/v1/**'),
        ],
        priority: 100,
    ),
];

$engine = new RouterEngine(new RouteTable($routes), policy: DecisionPolicy::FIRST);

$ctx = new RoutingContext(protocol: 'https', host: 'payments.example.com', path: '/v1/charge', method: 'GET');
$outcome = $engine->route($ctx);

if ($outcome->ok) {
    $selected = $outcome->decision->selected[0] ?? null;
    // $selected->target contains your adapter payload
}
```

## Documentation

- Architecture: [docs/architecture.md](docs/architecture.md)
- Routing model: [docs/routing.md](docs/routing.md)
- HTTP router (legacy adapter): [docs/http.md](docs/http.md)
- HTTP controllers (binding + request/response cookbook): [docs/http-controller.md](docs/http-controller.md)
- Extensions: [docs/extensions.md](docs/extensions.md)

## Examples

- Gateway-style routing + tracing: [examples/gateway-routing.php](examples/gateway-routing.php)
- Gateway-style middleware pipeline around target execution: [examples/gateway-middleware.php](examples/gateway-middleware.php)
- Hot reload via `PhpRuleLoader` + `RouterEngine::reload()`: [examples/hotreload.php](examples/hotreload.php)
- HTTP attribute routing (basic): [examples/http-basic.php](examples/http-basic.php)
- HTTP attribute routing (header constraints): [examples/http-headers.php](examples/http-headers.php)
- HTTP attribute routing (priority + fallback): [examples/http-priority-fallback.php](examples/http-priority-fallback.php)
- Real HTTP app (public/index.php style): [examples/http-app/README.md](examples/http-app/README.md)

## Tests

Run the lightweight (dependency-free) tests:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/run.php
```

## Design Philosophy

- Explicit over implicit: matching, ordering, and decision policy are visible.
- Configuration as code: routes can be loaded as typed objects and tested.
- Predictable execution: deterministic tie-breaking (priority → specificity → id).
- Minimal magic: adapters own side-effects; the core engine stays pure.

## License

MIT. See [LICENSE](LICENSE).

