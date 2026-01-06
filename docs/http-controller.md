# HTTP Controller Cookbook

This document is a practical guide for writing controllers for the legacy HTTP router (`Il4mb\\Routing\\Router`).

It covers:

- controller method signatures and how arguments are bound
- how to read inputs (route params, query, body, JSON, files, cookies, headers)
- how to write outputs (string/array responses, status codes, headers, cookies, redirects)
- how to use `$next` and middlewares

For router lifecycle, production options, and routing semantics, see [docs/http.md](docs/http.md).

If your app is mounted under a URL prefix (e.g. you serve the app from `/api`), configure it explicitly on the router:

```php
$router = new Router(options: [
  'basePath' => '/api',
  'autoDetectFolderOffset' => false,
]);
```

## 1) Controller Signature (Argument Binding)

A controller method is invoked through the `Callback` binder. You can declare parameters in any order.

Common signature:

```php
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Map\Route;

final class UserController
{
    #[Route(Method::GET, '/users/{id}')]
    public function show(int $id, Request $req, Response $res, callable $next)
    {
        return ['id' => $id];
    }
}
```

### What can be injected

- **Route params** by *parameter name*:
  - `{id}` binds to `$id`
  - supports scalar typing + union typing (see below)
- **Request/Response** objects by type-hint:
  - `Request $req`, `Response $res`
- **`$next`** continuation:
  - `callable $next` or `\Closure $next`

### Binding rules (short version)

The binder applies this precedence:

1) If `$paramName` matches a capture AND the param type is scalar-ish (`string|int|float|bool|mixed` or unions containing them), bind from the capture.
2) Else, inject from runtime arguments by type (e.g. `Request`, `Response`, `callable` for `$next`).
3) Else:
   - use the parameter default value if present
   - else use `null` (may raise a PHP type error if your signature disallows it)

### Typed route param casting

Captures are strings from the path, but are cast when you type-hint:

- `int $id`: `"123"` → `123`
- `float $x`: `"3.14"` → `3.14`
- `bool $flag`: `on/yes/true/1` → `true`, `off/no/false/0` → `false`
- `string $name`: stays a string
- `int|string $id`: prefers `int` if numeric; otherwise `string`

### URL decoding note

Route captures are decoded with path-safe semantics (`rawurldecode`).

That means `+` stays `+` (unlike `urldecode`, which turns `+` into a space).

## 1.1) Converters / Parameter Resolvers (Value Objects)

If you want your controller to accept domain-specific types (value objects) instead of raw strings/ints, you can.

By default, the binder includes a resolver that can build objects from a matching capture:

- static `::fromString(string)`
- static `::from(string)`
- or a public constructor that accepts a single scalar

Example:

```php
final class UserId
{
  public function __construct(public string $value) {}
  public static function fromString(string $value): self { return new self($value); }
}

#[Route(Method::GET, '/users/{id}')]
public function show(UserId $id, Request $req, Response $res, callable $next)
{
  return ['id' => $id->value];
}
```

You can also register custom resolvers on the router (e.g. build an object from headers, cookies, or multiple captures)
via the router option `parameterResolvers`.

## 2) Read Request Method + URL

```php
$method = $req->method; // e.g. "GET"
$path = $req->uri->getPath();
$host = $req->uri->getHost();
$protocol = $req->uri->getProtocol(); // http/https
$queryString = $req->uri->getQuery();
$fragment = $req->uri->getFragment();
```

Convenience:

```php
if ($req->isMethod('POST')) { /* ... */ }
```

## 3) Read Query Parameters (GET)

The request stores query params parsed from `$_GET`.

```php
$page = $req->getQuery('page');
$search = $req->getQuery('q');
```

You can also use `get()` (see “Unified get()” below).

## 4) Read POST Body / JSON Body

### Form data (POST)

```php
$email = $req->getBody('email');
$password = $req->getBody('password');
```

### JSON body

If the request is not multipart, the implementation attempts `json_decode(file_get_contents('php://input'), true)` and merges it into `__body`.

```php
$user = $req->getBody('user');
// For JSON like: {"user": {"name": "A"}}
```

### Nested form fields

Nested form names like `items[0][price]` are parsed into nested arrays.

```php
$items = $req->getBody('items');
```

## 5) Read Files (multipart/form-data)

Use:

```php
$file = $req->getFile('avatar');
```

Returned structure depends on whether you uploaded 1 file or many:

- single file: `['type','name','tmp_name','size','error']`
- multi file: list of the same file arrays

## 6) Read Cookies

```php
$token = $req->getCookie('token');
```

## 7) Read Headers

Headers are stored in a case-insensitive `ListPair`.

```php
$accept = $req->headers['accept'];
$role = $req->headers['x-role'];
$contentType = $req->headers['content-type'];
```

Helpers:

```php
if ($req->isAjax()) { /* ... */ }
if ($req->isContent('application/json')) { /* ... */ }
```

## 8) Unified get() (Props → Body → Query)

`Request::get($key)` resolves in this order:

1) custom request props set via `$req->set()`
2) body (`getBody()`)
3) query (`getQuery()`)

Example:

```php
// Will return, in order: props['userId'] then body['userId'] then query['userId']
$userId = $req->get('userId');
```

Get everything (debug):

```php
$all = $req->get('*');
```

Set custom props:

```php
$req->set('tenantId', 't-123');
$tenantId = $req->get('tenantId');
```

Note: you cannot set fixed keys like `__body`, `__queries`, `__files`, `__cookies`.

## 9) Write Response

### Return value shortcut

If your controller returns a non-null value, the router sets it as response content:

- return `string` → response body is that string
- return `array` → response will be JSON when `Response::send()` is used

Example:

```php
return ['ok' => true];
```

### Manual response control

```php
$res->setCode(201);
$res->setContentType('application/json');
$res->setContent(['created' => true]);
```

Set headers:

```php
$res->headers['x-request-id'] = 'abc';
```

Set cookies:

```php
$res->setCookie('token', 'xyz', ['httponly' => true, 'secure' => true, 'path' => '/']);
```

Redirect:

```php
return Response::redirect('/login');
```

## 10) Using `$next` (Chaining)

`$next()` continues to the next matched route.

This is only useful when you configure the router with:

- `decisionPolicy='chain'` (multiple routes may be selected)

Example middleware-style routing:

```php
#[Route(Method::GET, '/chain', priority: 10)]
public function a(Request $req, Response $res, callable $next)
{
    $res->setContent(($res->getContent() ?? '') . 'A');
    $next();
}

#[Route(Method::GET, '/chain', priority: 0)]
public function b(Request $req, Response $res, callable $next)
{
    $res->setContent(($res->getContent() ?? '') . 'B');
}
```

If you use `decisionPolicy='first'`, you typically do **not** call `$next()`.

## 11) Route Middlewares (Per Route)

The attribute supports a middleware list:

```php
#[Route(Method::POST, '/users', middlewares: [AuthMiddleware::class])]
public function create(Request $req, Response $res, callable $next)
{
    // ...
}
```

Middlewares implement `Il4mb\\Routing\\Middlewares\\Middleware` and run before the controller.

## 12) Observability (Debug Trace)

If router option `debugTrace=true`, the router stores engine trace output into the request:

- `$req->get('__route_trace')`
- `$req->get('__route_decision')`

This is useful when troubleshooting why a route did/didn’t match.
