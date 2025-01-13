<?php

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Middlewares\Middleware;
use Il4mb\Routing\Router;

ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

require_once __DIR__ . "/vendor/autoload.php";

class AdminAware implements Middleware
{
    function handle(Request $request, Closure $next): Il4mb\Routing\Http\Response
    {
        return $next($request);
    }
}


class AdminController
{

    #[Route(Method::GET, "/")]
    function home()
    {
        return ["Hello World"];
    }


    #[Route(Method::GET, "/2/{any[1]}", [AdminAware::class])]
    function home2($any)
    {
        return "hhh " . $any;
    }
}



$obj = new AdminController();
$router = new Router([], [
    "throwOnDuplicatePath" => false
]);
$router->addRoute(new AdminController());

$response = $router->dispatch(Request::getInstance());

echo $response->send();
