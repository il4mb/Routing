<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Router;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;

final class FallbackController
{
    #[Route(Method::GET, '/fb', host: 'api.example.com')]
    public function api(Request $req, Response $res, callable $next): string
    {
        return 'api route';
    }

    #[Route(Method::GET, '/fb', fallback: true)]
    public function fallback(Request $req, Response $res, callable $next): string
    {
        return 'fallback route';
    }
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/fb';
$_SERVER['HTTP_HOST'] = 'www.example.com';
$_SERVER['SCRIPT_NAME'] = '/index.php';

$router = new Router(options: [
    'manageHtaccess' => false,
    'decisionPolicy' => 'first',
]);
$router->addRoute(new FallbackController());

$req = new Request(['clearState' => false]);
$res = $router->dispatch($req);

echo $res->getContent() . "\n";
