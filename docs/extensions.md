# Extensions & Integrations

This project is designed so the routing **core** stays stable while matchers, adapters, and observability evolve.

## Adding a New Matcher

Create a class that implements `Il4mb\Routing\Engine\Matchers\Matcher`:

- `name(): string` should be stable and descriptive
- `match(RoutingContext): MatchResult` must be pure (no side effects)

Example: match by a custom attribute (already provided as `AttributeMatcher`).

### Guidance

- Return a specific `reason` on mismatch. This shows up in traces.
- Provide deterministic `specificity` so tie-breaking remains predictable.

## Adding a New Target Type

A route `target` is intentionally `mixed`.

Common patterns:

- **Controller callback** (HTTP): a `Map\Route` instance with `Callback`
- **Upstream selection** (proxy): `['cluster' => 'payments-eu', 'timeout_ms' => 1500]`
- **Mail policy** (SMTP): `['queue' => 'bulk', 'dmarc' => 'enforce']`

Your adapter decides how to interpret and execute the target.

## Integrating Scripting / “Configuration as Code”

The recommended approach in PHP is:

- Define routes as PHP code (typed objects) rather than untyped strings.
- Load them from a file and reload on a timer, signal, or file change.

The core includes `Il4mb\Routing\Engine\Loaders\PhpRuleLoader` which loads a PHP file that returns:

- `list<RouteDefinition>`
- or `callable(): list<RouteDefinition>`

This gives you:

- full programmability (conditions as closures)
- predictable behavior (you can unit test the rule file)
- a straightforward reload mechanism

## Lifecycle Hooks

Implement `Il4mb\Routing\Engine\Hooks\RoutingHook` to plug into the pipeline:

- `preRoute`: normalize/enrich context
- `postMatch`: inspect candidates and selection
- `postRoute`: finalize tracing/metrics
- `onError`: central error reporting

Typical use cases:

- attach request ids / tenant ids into context
- collect metrics (match counts, latency)
- log ambiguous routing as operational incidents

## Observability: Tracing and Metrics

### Tracing

Implement `Il4mb\Routing\Engine\Tracing\Tracer`.

- `NullTracer` is the default.
- `ArrayTracer` is useful for debug/CLI tooling.

Production deployments usually forward trace events to:

- PSR-3 logs (adapter responsibility)
- metrics backends (Prometheus/StatsD)
- distributed tracing (OpenTelemetry)

### Metrics Hook (Pattern)

A simple pattern is to implement a `RoutingHook` that increments counters:

- `routing_candidates_total`
- `routing_matches_total`
- `routing_ambiguous_total`
- `routing_no_match_total`

The core stays dependency-free; integrations own their telemetry clients.

## Middleware (Execution Layer)

Routing and execution are separate concerns. The engine selects a route; adapters execute targets.

For infrastructure use cases, it is common to apply policy middleware *around execution*:

- authentication / authorization
- rate limits
- tenant selection
- request id injection

This repo provides an engine-level middleware pipeline:

- `Il4mb\Routing\Engine\Middlewares\Middleware`
- `Il4mb\Routing\Engine\Middlewares\MiddlewareExecutor`

See [examples/gateway-middleware.php](examples/gateway-middleware.php) for a full runnable example.

## HTTP Router Compatibility Notes

The legacy `Il4mb\Routing\Router`:

- still supports attribute routes (`#[Route(...)]`)
- compiles those routes into engine rules internally
- executes selected routes using the existing middleware/interceptor model

Production guidance:

- disable `.htaccess` management by setting `manageHtaccess=false`
- enable `debugTrace=true` temporarily when diagnosing routing issues
- consider `decisionPolicy=error_on_ambiguous` for security-sensitive routes
