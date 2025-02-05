<?php
namespace Il4mb\Routing;

use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Il4mb\Routing\Map\Route;
use Throwable;

/**
 * @each Method return true to brak
 */
interface Interceptor
{
    
    function onAddRoute(Route &$route): bool;
    
    function onInvoke(Route &$route): bool;

    function onBeforeInvoke(Route &$route): bool;

    function onDispatch(Request &$request, Response &$response): bool;
    
    function onFailed(Throwable $t, Request &$request, Response &$response);
}
