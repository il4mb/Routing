<?php

namespace Il4mb\Routing\Middlewares;

use Closure;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response as HttpResponse;
use Il4mb\Routing\Map\Route;
use ReflectionClass;

class MiddlewareExecutor
{
    private array $middlewares = [];

    /**
     * Middleware Executor
     *
     * @param array<class-string<\Il4mb\Routing\Middlewares\Middleware>> $middlewares
     */
    public function __construct(array $middlewares)
    {
        $this->middlewares = array_map(
            function ($className) {
                // Check if the class implements the Middleware interface
                if (in_array(Middleware::class, class_implements($className))) {
                    return (new ReflectionClass($className))->newInstanceWithoutConstructor();
                }

                throw new \InvalidArgumentException(
                    sprintf("Class '%s' must implement the Middleware interface.", $className)
                );
            },
            $middlewares
        );
    }

    /**
     * Executes the middleware stack.
     *
     * @param \Il4mb\Routing\Http\Request $request
     * @param \Closure $next
     * @return \Il4mb\Routing\Http\Response
     */
    public function __invoke(Request $request)
    {
        $next = $this->createNextClosure(function () use ($request) {
            return $request;
        }, 0);
        return $next($request);
    }

    /**
     * Creates a Closure to handle middleware chaining.
     *
     * @param \Closure $default
     * @param int $index
     * @return \Closure
     */
    private function createNextClosure(Closure $default, int $index): Closure
    {
        return function (Request $request) use ($default, $index) {
            if ($index >= count($this->middlewares)) {
                // If there are no more middlewares, call the default handler
                return $default($request);
            }

            // Get the current middleware
            $middleware = $this->middlewares[$index];

            // Recursively create the next closure for the next middleware
            $next = $this->createNextClosure($default, $index + 1);

            // Call the middleware's handle method
            return $middleware->handle($request, $next);
        };
    }

    static function execute(Route $route, Request $request): void
    {
        $executor = new static($route->middlewares ?? []);
        $executor($request);
    }
}
