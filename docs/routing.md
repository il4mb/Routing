# Routing Model

This document defines the routing model and the deterministic routing pipeline.

## Routing Context

Routing operates on a single input object: `Il4mb\Routing\Engine\RoutingContext`.

A context is intentionally protocol-agnostic:

- `protocol`: `http`, `https`, `smtp`, `tcp`, …
- `host`: domain / virtual host / SNI / tenant key
- `path`: request path or comparable “address” in your protocol
- `method`: optional request verb (HTTP method, custom opcode)
- `headers`: optional header bag
- `attributes`: arbitrary metadata (TLS info, auth claims, geo, client tags)

Adapters populate `RoutingContext` from real requests.

## Route Definition

A route is a rule with:

- **matchers**: a list of predicates evaluated with AND semantics
- **priority**: higher wins
- **specificity**: computed by matchers (e.g. static path beats wildcard)
- **fallback**: only considered when no non-fallback routes match
- **target**: opaque payload returned to the adapter (controller callback, upstream id, policy object)

## Matching & Patterns

### Path Pattern Syntax

The engine supports patterns designed for infrastructure workloads:

- Static: `/api/v1/users`
- Named segment: `/users/{id}`
- Named segment with regex: `/users/{id:[0-9]+}`
- Expected list (legacy): `/svc/{tier[gold,silver]}`
- Single segment wildcard: `/files/*`
- Greedy wildcard: `/proxy/**`
- Greedy named (legacy): `/{path.*}`
- Greedy named (explicit): `/{rest:**}`

Named captures are exposed to adapters via `MatchResult::captures`.

### Host / Protocol / Header Matching

- Host matcher supports `api.example.com` and `*.example.com`.
- Protocol matcher compares `RoutingContext::protocol`.
- Header matcher can require presence or exact value.

### Conditional Routes

A route may define a runtime condition (`Closure(RoutingContext): bool`).

This is the intended extensibility point for “context-aware routing”:

- Match by authenticated identity / tenant
- Match by geo / ASN
- Match by request metadata (mTLS, cipher suite)

## Deterministic Routing Pipeline

The engine has a fixed pipeline:

1) **Pre-route**: normalize/enrich the context (hooks)
2) **Match**: evaluate candidates, compute captures, priority, specificity
3) **Decision**: select route(s) using a policy
4) **Post-route**: emit hooks, finalize decision

Each stage is explicit and traceable.

## Decision Policies

The engine supports three deterministic policies:

- `first`: selects the single best route (priority → specificity → id)
- `chain`: returns all matching non-fallback routes in deterministic order (legacy-style execution)
- `error_on_ambiguous`: fails if more than one non-fallback route matches

Infrastructure guidance:

- API gateways typically use `first`.
- Middleware-style HTTP routing (where multiple handlers run) uses `chain`.
- Security-sensitive routing often uses `error_on_ambiguous`.

## Fallback Semantics

Fallback routes (`fallback=true`) are only selected when **no** non-fallback routes match.

This avoids a common production failure mode where a broad fallback rule accidentally overrides a specific match.

## Errors

The engine raises structured errors:

- `NoRouteMatchedException`: nothing matched the context
- `AmbiguousRouteException`: multiple matches when strictness requires uniqueness
- `InvalidRouteDefinitionException`: invalid rule definitions

Callers choose *fail-open vs fail-closed* behavior via `FailureMode`.

## Observability

The engine emits structured trace events via `Tracer`:

- candidate counts
- match rejections and reasons
- final decision (selected route ids)

The legacy HTTP router can store these events into the `Request` object when `debugTrace=true`.
