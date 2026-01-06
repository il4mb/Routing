<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Router;

final class AppController
{
    #[Route(Method::GET, '/health')]
    public function health(): array
    {
        return ['ok' => true];
    }

    #[Route(Method::GET, '/users/{id}')]
    public function showUser(int $id, Request $req): array
    {
        return [
            'id' => $id,
            'method' => $req->method,
            'path' => $req->uri->getPath(),
        ];
    }

    #[Route(Method::GET, '/secure', headers: ['x-role' => 'admin'], priority: 10)]
    public function secureAdmin(): array
    {
        return ['role' => 'admin'];
    }

    // Fallback: only used if nothing else matches.
    #[Route(Method::GET, '/{path.*}', fallback: true)]
    public function notFound(string $path, Response $res): array
    {
        $res->setCode(404);
        return ['error' => 'not_found', 'path' => $path];
    }
}

$router = new Router(options: [
    // Avoid filesystem side-effects in examples.
    'manageHtaccess' => false,

    // Typical HTTP apps want a single best match.
    'decisionPolicy' => 'first',

    // Standardize errors (e.g. exceptions) as JSON.
    'errorFormat' => 'json',
    'errorExposeDetails' => false,

    // Optional: if you mount this app under /api, set:
    // 'basePath' => '/api',
    // 'autoDetectFolderOffset' => false,
]);

$router->addRoute(new AppController());

$request = new Request();
$response = $router->dispatch($request);

echo $response->send();
