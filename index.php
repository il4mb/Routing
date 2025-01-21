<?php

use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Router;

ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

require_once __DIR__ . "/vendor/autoload.php";



class Controller
{

    #[Route(Method::GET, "/")]
    function get()
    {
        return ["From Get"];
    }


    #[Route(Method::POST, "/")]
    function post(Request $req)
    {
        echo json_encode($req->get("*"));
        return ["From Post"];
    }

    #[Route(Method::PUT, "/")]
    function put(Request $req)
    {
        //    echo "From PUT\n";
        echo json_encode($req->get("*"));
        //return ["From Put"];
    }
}


$router = new Router(options: [
    "throwOnDuplicatePath" => false
]);
$router->addRoute(new Controller());
$response = $router->dispatch(Request::getInstance());
echo $response->send();
