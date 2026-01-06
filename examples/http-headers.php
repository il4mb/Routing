<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Router;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;

final class SecureController
{
    #[Route(Method::GET, '/secure', headers: ['x-role' => 'admin'], priority: 10)]
    public function admin(Request $req, Response $res, callable $next): string
    {
        return 'admin page';
    }

    #[Route(Method::GET, '/secure', headers: ['x-role' => 'user'], priority: 5)]
    public function user(Request $req, Response $res, callable $next): string
    {
        return 'user page';
    }
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/secure';
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$router = new Router(options: [
    'manageHtaccess' => false,
    'decisionPolicy' => 'first',
]);
$router->addRoute(new SecureController());

$req = new Request(['clearState' => false]);
$req->headers['x-role'] = 'admin';

$res = $router->dispatch($req);
echo $res->getContent() . "\n";
