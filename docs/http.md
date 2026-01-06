# HTTP Router (Legacy Attribute Adapter)

This project includes a legacy HTTP router (`Il4mb\\Routing\\Router`) that supports attribute routes (`#[Route(...)]`) while delegating matching/decision-making to the core engine.

The goal of this adapter is:

- keep the public API familiar for HTTP controller routing
- keep routing decisions deterministic and observable
- keep the core engine protocol-agnostic and dependency-free

## Quick Start

See runnable examples:

- [examples/http-basic.php](examples/http-basic.php)
- [examples/http-headers.php](examples/http-headers.php)
- [examples/http-priority-fallback.php](examples/http-priority-fallback.php)

For a controller-focused guide (binding + reading inputs + writing responses), see:

- [docs/http-controller.md](docs/http-controller.md)

## Router Lifecycle (High-Level)

When you call `Router::dispatch(Request $request)`:

1) The router builds an engine `RoutingContext` from the HTTP request.
2) Attribute routes are compiled (and cached) into an engine `RouteTable`.
3) `RouterEngine::route()` selects the best route(s) deterministically.
4) The legacy execution pipeline runs:
   - interceptors (`onDispatch` → `onBeforeInvoke` → `onInvoke`)
   - per-route HTTP middlewares (`Il4mb\\Routing\\Middlewares\\MiddlewareExecutor`)
   - controller callback execution (`Il4mb\\Routing\\Callback`)

## Production Options

Recommended for production:

- `manageHtaccess=false` (avoid filesystem side-effects)
- `decisionPolicy='first'` for typical HTTP apps
- `decisionPolicy='error_on_ambiguous'` for security-sensitive routing
- `debugTrace=false` unless troubleshooting

Example:

```php
$router = new Router(options: [
    'manageHtaccess' => false,
    'decisionPolicy' => 'first',
    'debugTrace' => false,
]);
```

## Debug Trace

When `debugTrace=true`, the router stores engine trace information into the request:

- `Request::get('__route_trace')`: list of structured trace events
- `Request::get('__route_decision')`: selected route ids + reason + policy

This is intended for troubleshooting and building tooling.

## 405 Method Not Allowed

When no route matches, the router will additionally check whether the same path/constraints match **for a different HTTP method**.

If so, it responds with:

- status code `405 Method Not Allowed`
- an `Allow: ...` header listing the supported methods

This makes the legacy HTTP adapter behave more like a production HTTP router.

## Attribute Route Fields

The attribute `#[Route(...)]` supports:

- `method` (string)
- `path` (pattern)
- `priority` (higher wins)
- `fallback` (only considered if no non-fallback matches)
- `host` (exact or `*.example.com`)
- `protocol` (`http`, `https`, ...)
- `headers` (exact match or presence)
- `metadata` (arbitrary array for integrations)

## Controller Signature Binding (Flexible)

The router executes controller methods via the `Callback` binder.

### What gets injected

A controller method can accept:

- route captures by **parameter name** (e.g. `{id}` binds to `$id`)
- `Request` and `Response` objects by type-hint
- `$next` as `callable` (or `Closure`) to continue the chain

Example:

```php
#[Route(Method::GET, '/users/{id}')]
public function show(int $id, Request $req, Response $res, callable $next)
{
    return ['id' => $id];
}
```

### Binding Precedence (Contract)

When building the argument list for your controller method, the binder applies a deterministic precedence:

1) If a parameter name matches a route capture (e.g. `{id}` → `$id`) AND the parameter type is scalar-ish (`string|int|float|bool|mixed` or unions containing them), bind from the capture.
2) Otherwise, try to inject from runtime arguments by type:
    - `Request`, `Response`, or other objects passed into the callback
    - `callable` / `Closure` for `$next`
3) If nothing matches:
    - use the PHP default value, if present
    - otherwise, if the parameter allows null (`?T`), use `null`
    - otherwise, use `null` (which may trigger a PHP type error if the signature disallows it)

This ensures class-typed parameters (like `Request $request`) are never accidentally populated from a same-named capture like `/{request}`.

### Scalar typing rules

Captures come from paths and are typically strings. The binder will cast when you type-hint:

- `int`: numeric strings like `"123"` → `123`
- `float`: numeric strings like `"3.14"` → `3.14`
- `bool`: `on/yes/true/1` → `true`, `off/no/false/0` → `false`
- `string`: kept as string

### Union types

Union types are supported (PHP 8):

- `int|string $id` will prefer `int` if the capture looks like an integer; otherwise `string`.

### Nullable types

Nullable params are supported:

- `?string $x` can receive `null` if no capture value is available.

Defaults are respected:

```php
#[Route(Method::GET, '/opt')]
public function opt(?string $x = 'dflt', Request $req, Response $res, callable $next)
{
    return $x; // "dflt" when no capture exists
}
```

Note: optional path segments depend on the path pattern support in the matcher.

### Defaults

If a parameter is not matched and has a default value, the default is used.

## Notes on URL Decoding

Captures are decoded using path-safe semantics (`rawurldecode`).

This means `+` stays `+` (unlike `urldecode`, which treats `+` as space).

## Tests

Run the lightweight test suite:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/run.php
```
