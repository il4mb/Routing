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

    #[Route(Method::GET, "/hallo")]
    function get1()
    {
        return ["Halllo From Halaman Hallo"];
    }


    #[Route(Method::POST, "/")]
    function post(Request $req)
    {
        echo json_encode($req->get("*"));
        return ["From Post"];
    }

    #[Route(Method::PUT, "/{adios}/")]
    function put(Request $req, $adios, $adios1)
    {
        $req->set("view", 135);
        return ["Ini Kontent"];
    }

    #[Route(Method::DELETE, "/*")]
    function delete()
    {
        return ["From Delete"];
    }
}


$router = new Router();
$router->addRoute(new Controller());
$response = $router->dispatch(new Request());
echo $response->send();
