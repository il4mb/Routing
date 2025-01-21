<?php

namespace Il4mb\Routing;

use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use InvalidArgumentException;
use ReflectionClass;
use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Middlewares\MiddlewareExecutor;

class Router implements Interceptor
{

    private readonly string $routeOffset;

    /**
     * Summary of routers
     * @var array<Route> $routes
     */
    private array $routes;

    /**
     * Summary of __construct
     * @var array<Interceptor> $interceptors
     */
    private array $interceptors = [];


    /**
     * @var array<string, mixed> $options
     */
    private readonly array $options;

    /**
     * Summary of __construct
     * @param array $interceptors
     * @param array $options - options for router
     *              - *throwOnDuplicatePath*   default true
     *              - *autoDetectFolderOffset* default true
     *
     * 
     */
    function __construct(array $interceptors = [], array $options = [])
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = null;
        foreach ($traces as $trace) {
            if (isset($trace['file'])) {
                $file = $trace['file'];
                break;
            }
        }

        if (is_string($root) && is_string($file)) {
            $root = preg_replace('/\\\\|\\//im', '/', $root);
            $path = preg_replace('/\\\\|\\//im', '/', dirname($file));
            $offset = str_replace($root, "", $path);
            $this->routeOffset = $offset;
        } else {
            $this->routeOffset = "";
        }

        $htaccessFile = rtrim($root, "\/") . "/" . trim($this->routeOffset, "\/") . "/.htaccess";
        if (is_string($file) && !file_exists($htaccessFile)) {
            $fileName = trim($this->routeOffset, "\/") . "/" . basename($file);
            $htaccess = <<<EOS
# THIS FILE ARE GENERATE BY <IL4MB/ROUTING> 
# YOU CAN MODIFY ANY THING BUT MAKE SURE EACH REQUEST ARE POINT TO INDEX.PHP
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /$fileName [NC,L,QSA]
EOS;
            file_put_contents($htaccessFile, $htaccess);
        }


        $this->interceptors = [
            $this,
            ...$interceptors
        ];
        $this->routes = [];
        $this->options = [
            "throwOnDuplicatePath" => true,
            "autoDetectFolderOffset" => true,
            ...$options
        ];
    }

    function removeInterceptor(Interceptor $interceptor)
    {
        foreach ($this->interceptors as $key => $existingInterceptor) {
            if ($existingInterceptor === $interceptor) {
                unset($this->interceptors[$key]); // Remove the matching interceptor
                $this->interceptors = array_values($this->interceptors); // Reindex the array
                return;
            }
        }
    }

    function addInterceptor(Interceptor $interceptor)
    {
        $this->interceptors[] = $interceptor;
    }


    /**
     * Summary of addRoute
     * @param mixed|Route $obj Add route object or controller class
     * @return void
     */
    function addRoute(mixed $obj)
    {
        if ($obj instanceof Route) {
            if ($this->options["throwOnDuplicatePath"]) {
                $duplicates = array_filter(
                    $this->routes,
                    fn($route) => $route->path === $obj->path
                );
                if (count($duplicates) > 0) throw new InvalidArgumentException("Cannot add path \"$obj->path\", same path already added in collection.");
            }

            $path = "/" . trim($this->routeOffset, "\/") . "/" . trim($obj->path, "\/");
            $this->routes[] = $obj->clone([
                "path" => $path,
                "callback" => $obj->callback
            ]);
        } else {
            $reflector = new ReflectionClass($obj);
            foreach ($reflector->getMethods() as $method) {
                foreach ($method->getAttributes() as $defAttr) {
                    if ($defAttr->getName() == Route::class) {
                        $routeInstance = $defAttr->newInstance();
                        $reflector = new ReflectionClass($routeInstance);
                        if ($reflector->hasProperty('callback')) {
                            $property = $reflector->getProperty('callback');
                            $property->setAccessible(true);
                            $property->setValue($routeInstance, Callback::create($method->getName(), $obj));
                            $property->setAccessible(false);
                        }
                        $this->addRoute($routeInstance);
                    }
                }
            }
        }
    }


    /**
     * Summary of dispatch
     * @param \Il4mb\Routing\Http\Request $request
     * @return \Il4mb\Routing\Http\Response
     */
    function dispatch(Request $request): Response
    {

        $routes = $this->routes;
        // sort descending by path
        usort($routes, fn($a, $b) => strcmp($b->path, $a->path));

        $uri = $request->uri;
        // find match route
        $route = array_values(array_filter($routes, fn($route) => $request->method === $route->method && $uri->matchRoute($route)));
        $response = new Response(empty($route) ? null : $route[0]);
        // if not found
        if (empty($route)) $response->setCode(Code::NOT_FOUND);

        // invoke interceptor
        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->onDispatch($request, $response)) break;
        }

        $executor = new MiddlewareExecutor($route[0]->middlewares ?? []);
        $response = $executor($request, function () use ($response) {
            return $response;
        });

        return $response;
    }


    function onAddRoute(Route $route): bool
    {
        return false; // return false to pass to next interceptor
    }
    function onBeforeInvoke(Route $route): bool
    {
        return false; // return false to pass to next interceptor
    }
    function onInvoke(Route $route): bool
    {
        return false; // return false to pass to next interceptor
    }

    function onDispatch(Request $request, Response $response): bool
    {

        $route = $response->route;
        if ($route) {
            $params = $route->parameters;
            if ($route && $callback = $route->callback) {
                $content = $callback(...[...$params, $request, $response]);
                $response->setContent($content);
            }
        }

        return false; // return false to pass to next interceptor
    }


    function __debugInfo()
    {
        return [
            "routes" => $this->routes
        ];
    }
}
