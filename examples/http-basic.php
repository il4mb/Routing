<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Router;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;

final class HelloController
{
    #[Route(Method::GET, '/hello/{name}')]
    public function hello(string $name, Request $req, Response $res, callable $next): string
    {
        return 'Hello ' . $name;
    }
}

// CLI demo (simulates a web request). In a real web app, you won't set $_SERVER manually.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/hello/budi+plus';
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$router = new Router(options: [
    'manageHtaccess' => false,
    'debugTrace' => true,
    'decisionPolicy' => 'first',
]);
$router->addRoute(new HelloController());

$req = new Request(['clearState' => false]);
$res = $router->dispatch($req);

echo $res->getContent() . "\n";

$decision = $req->get('__route_decision');
if (is_array($decision)) {
    echo "Selected: " . implode(', ', $decision['selected'] ?? []) . "\n";
    echo "Reason: " . ($decision['reason'] ?? '') . "\n";
}
