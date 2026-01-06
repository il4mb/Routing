# Architecture

This project provides a routing engine that can be embedded into infrastructure software (API gateways, reverse proxies, programmable mail servers, and other request/flow dispatchers).

The repository is intentionally split into two layers:

- **Core Engine** (`src/Engine/*`): deterministic routing, pure matching, explicit decision-making, trace emission. The core is designed to be testable and side-effect free.
- **Adapters / Integrations** (`src/Adapters/*`, plus the existing HTTP classes): convert environment-specific requests into a `RoutingContext`, and adapt the selected routes into whatever execution model you need (HTTP controllers, proxy upstream selection, mail policy decisions, etc.).

## High-Level Diagram (Text)

A request (or message) flows through a fixed pipeline:

```
[Incoming Request]
      |
      v
[Adapter: build RoutingContext]
      |
      v
[Pre-Route Hooks]
      |
      v
[Match Phase] -- evaluates candidate routes and produces ranked matches
      |
      v
[Decision Phase] -- selects best route or a deterministic chain
      |
      v
[Post-Route Hooks]
      |
      v
[Adapter: execute target / return decision]
```

Observability is built into the pipeline by design:

- The engine emits structured events to a `Tracer`.
- The decision result includes candidates, ordering, and the final selection.

## Component Responsibilities

### Core Types

- **`RoutingContext`**: immutable-ish input to routing (protocol, host, path, method, headers, attributes).
- **`RouteDefinition`**: a single route rule: matchers + target + priority/fallback metadata.
- **`Matcher`** + **`MatchResult`**: explicit match contract and capture output (e.g. path params).
- **`RouteTable`**: immutable route storage with lightweight indexing to avoid scanning obviously-irrelevant routes.
- **`RouterEngine`**: the deterministic pipeline: preRoute → match → resolve → postRoute.
- **`Decision`** + **`Outcome`**: structured outputs to make routing explainable.

### Adapters

- **HTTP adapter** (`src/Adapters/Http/*`):
  - `HttpContextFactory` builds a `RoutingContext` from the existing `Http\Request`.
  - `HttpRouteCompiler` turns attribute routes (`Map\Route`) into engine route definitions.

The legacy `Router` class uses these adapters internally so existing users do not have to rewrite their controllers.

## Execution Model vs Routing Model

The engine decides *what should handle* the input; execution is left to adapters.

This distinction is critical in infrastructure software:

- In an API gateway, a routing outcome may be an upstream cluster + policy bundle.
- In a mail server, a routing outcome may be a delivery queue + SPF/DKIM policy.
- In a reverse proxy, a routing outcome may be a target origin + rewrite rule.

## Design Guarantees

- **Deterministic**: tie-breaking is explicit (priority → specificity → stable id).
- **No hidden global state** in the core engine.
- **Traceable**: every stage can emit structured events.
- **Composable**: add new matchers and hooks without modifying the core router.

## Operational Notes

- In PHP-FPM style deployments, “reload without restart” typically means “reload rules per request” or “swap a route table in a long-lived worker.” The engine supports both patterns via `RouterEngine::reload()`.
- Side-effects like `.htaccess` management are legacy behavior of `Router`; production deployments often disable it with the `manageHtaccess` option.
