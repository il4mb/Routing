<?php

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Middlewares\Middleware;
use Il4mb\Routing\Router;

ini_set("log_errors", 1);
ini_set("error_log", "error.log");

require_once __DIR__ . "/vendor/autoload.php";
class Middle implements Middleware
{
    function handle(Request $request, Closure $next)
    {
    }
}
class Controller
{

    #[Route(Method::GET, "/{path.*}", middlewares: [Middle::class])]
    function get($path, Closure $next)
    {
        return $next();
        return ["From Get", $path];
    }

    #[Route(Method::GET, "/hallo/{world}")]
    function get13($world)
    {
        return ["Halllo From Halaman " . $world];
    }
}


$router = new Router();
$router->addRoute(new Controller());
$response = $router->dispatch(new Request());
echo $response->send();
