<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Router;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;

final class TestControllerBasic
{
    #[Route(Method::GET, '/hello/{name}')]
    public function hello(string $name, Request $req, Response $res, callable $next): string
    {
        return 'hi ' . $name;
    }
}

final class TestControllerHeaders
{
    #[Route(Method::GET, '/secure', headers: ['x-role' => 'admin'], priority: 10)]
    public function secureAdmin(Request $req, Response $res, callable $next): string
    {
        return 'admin';
    }

    #[Route(Method::GET, '/secure', headers: ['x-role' => 'user'], priority: 5)]
    public function secureUser(Request $req, Response $res, callable $next): string
    {
        return 'user';
    }
}

final class TestControllerPriorityChain
{
    #[Route(Method::GET, '/chain', priority: 10)]
    public function first(Request $req, Response $res, callable $next): void
    {
        $res->setContent(($res->getContent() ?? '') . 'A');
        $next();
    }

    #[Route(Method::GET, '/chain', priority: 0)]
    public function second(Request $req, Response $res, callable $next): void
    {
        $res->setContent(($res->getContent() ?? '') . 'B');
        $next();
    }
}

final class TestControllerFallback
{
    #[Route(Method::GET, '/fb', host: 'api.example.com')]
    public function apiOnly(Request $req, Response $res, callable $next): string
    {
        return 'api';
    }

    #[Route(Method::GET, '/fb', fallback: true)]
    public function fallback(Request $req, Response $res, callable $next): string
    {
        return 'fallback';
    }
}

final class TestControllerTypedScalars
{
    #[Route(Method::GET, '/num/{id}')]
    public function num(int $id, Request $req, Response $res, callable $next): string
    {
        return (string)($id + 1);
    }

    #[Route(Method::GET, '/flag/{v}')]
    public function flag(bool $v, Request $req, Response $res, callable $next): string
    {
        return $v ? 'true' : 'false';
    }

    #[Route(Method::GET, '/union/{id}')]
    public function union(int|string $id, Request $req, Response $res, callable $next): string
    {
        return is_int($id) ? 'int' : 'string';
    }
}

final class TestControllerBindingContract
{
    #[Route(Method::GET, '/opt')]
    public function opt(?string $x = 'dflt', Request $req = null, Response $res = null, callable $next = null): string
    {
        return $x ?? 'NULL';
    }

    #[Route(Method::GET, '/x/{request}')]
    public function requestObject(Request $request, Response $res, callable $next): string
    {
        return $request instanceof Request ? 'ok' : 'bad';
    }
}

final class TestControllerMethodNotAllowed
{
    #[Route(Method::GET, '/m')]
    public function get(Request $req, Response $res, callable $next): string
    {
        return 'get';
    }

    #[Route(Method::POST, '/m')]
    public function post(Request $req, Response $res, callable $next): string
    {
        return 'post';
    }
}

$tests = [];

$tests['basic attribute route + capture'] = function (): void {
    test_reset_http_env();
    $_SERVER['REQUEST_URI'] = '/hello/budi+plus';

    $router = new Router(options: [
        'manageHtaccess' => false,
        'debugTrace' => true,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerBasic());

    $req = new Request(['clearState' => false]);
    $res = $router->dispatch($req);

    test_equal($res->getContent(), 'hi budi+plus', 'Should decode path captures with rawurldecode semantics');

    $trace = $req->get('__route_trace');
    test_assert(is_array($trace) && count($trace) > 0, 'debugTrace should store __route_trace array');

    $decision = $req->get('__route_decision');
    test_assert(is_array($decision) && isset($decision['selected']), 'debugTrace should store __route_decision');
};

$tests['header constraints pick matching route'] = function (): void {
    test_reset_http_env();
    $_SERVER['REQUEST_URI'] = '/secure';

    $router = new Router(options: [
        'manageHtaccess' => false,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerHeaders());

    $req = new Request(['clearState' => false]);
    $req->headers['x-role'] = 'admin';

    $res = $router->dispatch($req);
    test_equal($res->getContent(), 'admin', 'Should select route constrained by headers');
};

$tests['priority order + chain executes in order'] = function (): void {
    test_reset_http_env();
    $_SERVER['REQUEST_URI'] = '/chain';

    $router = new Router(options: [
        'manageHtaccess' => false,
        'throwOnDuplicatePath' => false,
        'decisionPolicy' => 'chain',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerPriorityChain());

    $req = new Request(['clearState' => false]);
    $res = $router->dispatch($req);

    test_equal($res->getContent(), 'AB', 'CHAIN policy should execute higher priority first, then next');
};

$tests['fallback chosen only if no non-fallback matches'] = function (): void {
    test_reset_http_env();
    $_SERVER['REQUEST_URI'] = '/fb';
    $_SERVER['HTTP_HOST'] = 'www.example.com';

    $router = new Router(options: [
        'manageHtaccess' => false,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerFallback());

    $req = new Request(['clearState' => false]);
    $res = $router->dispatch($req);

    test_equal($res->getContent(), 'fallback', 'Fallback should be selected when non-fallback does not match');
};

$tests['route table recompiles after addRoute'] = function (): void {
    test_reset_http_env();

    $router = new Router(options: [
        'manageHtaccess' => false,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_open',
    ]);

    // No routes yet => fail-open means no throw.
    $_SERVER['REQUEST_URI'] = '/x';
    $req1 = new Request(['clearState' => false]);
    $res1 = $router->dispatch($req1);
    test_assert($res1 instanceof Response, 'Dispatch should return a Response');

    // Add route after first dispatch; it must mark compiledDirty and recompile.
    $router->addRoute(new TestControllerBasic());

    $_SERVER['REQUEST_URI'] = '/hello/test';
    $req2 = new Request(['clearState' => false]);
    $res2 = $router->dispatch($req2);
    test_equal($res2->getContent(), 'hi test', 'Router must recompile cached RouteTable after adding routes');
};

$tests['typed scalars (int/bool/union) bind from captures'] = function (): void {
    test_reset_http_env();

    $router = new Router(options: [
        'manageHtaccess' => false,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerTypedScalars());

    $_SERVER['REQUEST_URI'] = '/num/41';
    $req1 = new Request(['clearState' => false]);
    $res1 = $router->dispatch($req1);
    test_equal($res1->getContent(), '42', 'int param should cast from numeric capture');

    $_SERVER['REQUEST_URI'] = '/flag/on';
    $req2 = new Request(['clearState' => false]);
    $res2 = $router->dispatch($req2);
    test_equal($res2->getContent(), 'true', 'bool param should cast from boolean-ish capture');

    $_SERVER['REQUEST_URI'] = '/union/123';
    $req3 = new Request(['clearState' => false]);
    $res3 = $router->dispatch($req3);
    test_equal($res3->getContent(), 'int', 'union int|string should prefer int when capture is numeric');

    $_SERVER['REQUEST_URI'] = '/union/abc';
    $req4 = new Request(['clearState' => false]);
    $res4 = $router->dispatch($req4);
    test_equal($res4->getContent(), 'string', 'union int|string should use string for non-numeric capture');
};

$tests['binding contract: defaults + class injection win'] = function (): void {
    test_reset_http_env();

    $router = new Router(options: [
        'manageHtaccess' => false,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerBindingContract());

    // No capture => should use default, not null.
    $_SERVER['REQUEST_URI'] = '/opt';
    $req1 = new Request(['clearState' => false]);
    $res1 = $router->dispatch($req1);
    test_equal($res1->getContent(), 'dflt', 'Nullable typed param should still respect default value when capture is missing');

    // Capture name collides with param name "request", but param is class-typed Request.
    $_SERVER['REQUEST_URI'] = '/x/anything';
    $req2 = new Request(['clearState' => false]);
    $res2 = $router->dispatch($req2);
    test_equal($res2->getContent(), 'ok', 'Class-typed params should be injected, not bound from same-named captures');
};

$tests['405 method not allowed sets Allow header'] = function (): void {
    test_reset_http_env();
    $_SERVER['REQUEST_URI'] = '/m';
    $_SERVER['REQUEST_METHOD'] = 'PUT';

    $router = new Router(options: [
        'manageHtaccess' => false,
        'decisionPolicy' => 'first',
        'failureMode' => 'fail_closed',
    ]);
    $router->addRoute(new TestControllerMethodNotAllowed());

    $req = new Request(['clearState' => false]);
    $res = $router->dispatch($req);

    test_equal($res->getCode(), 405, 'Should respond with 405 when path matches but method does not');
    $allow = (string)($res->headers['allow'] ?? '');
    test_contains($allow, 'GET', 'Allow header should include GET');
    test_contains($allow, 'POST', 'Allow header should include POST');
};

$failed = 0;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "[PASS] $name\n";
    } catch (Throwable $t) {
        $failed++;
        echo "[FAIL] $name\n";
        echo $t->getMessage() . "\n";
    }
}

if ($failed > 0) {
    exit(1);
}

echo "All tests passed.\n";
