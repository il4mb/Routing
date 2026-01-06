<?php

namespace Il4mb\Routing;

use Closure;
use Exception;
use Il4mb\Routing\Adapters\Http\HttpContextFactory;
use Il4mb\Routing\Adapters\Http\HttpRouteCompiler;
use Il4mb\Routing\Engine\DecisionPolicy;
use Il4mb\Routing\Engine\Errors\AmbiguousRouteException;
use Il4mb\Routing\Engine\Errors\NoRouteMatchedException;
use Il4mb\Routing\Engine\FailureMode;
use Il4mb\Routing\Engine\Hooks\NoopHook;
use Il4mb\Routing\Engine\Hooks\RoutingHook;
use Il4mb\Routing\Engine\RouteTable;
use Il4mb\Routing\Engine\RouterEngine;
use Il4mb\Routing\Engine\Tracing\ArrayTracer;
use Il4mb\Routing\Engine\Tracing\NullTracer;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use InvalidArgumentException;
use ReflectionClass;
use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Middlewares\MiddlewareExecutor;
use Throwable;

class Router implements Interceptor
{
    private string $routeOffset;
    private array $routes = [];
    private array $interceptors = [];
    private array $options;

    private bool $compiledDirty = true;
    private ?RouteTable $compiledTable = null;
    private ?RoutingHook $engineHook = null;

    public function __construct(array $interceptors = [], array $options = [])
    {
        $this->initOption($options);
        $this->interceptors = [$this, ...$interceptors];
    }

    private function initOption(array $options = []): void
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? null;

        if (!isset($options['pathOffset'])) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? false;
            if ($scriptName) {
                $this->routeOffset = dirname(trim($scriptName));
            } else {
                $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $file = null;
                foreach ($traces as $trace) {
                    if (isset($trace['file']) && strtolower(basename($trace['file'])) === 'index.php') {
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
            }
        } else {
            $this->routeOffset = $options['pathOffset'];
        }

        $this->options = [
            'throwOnDuplicatePath' => true,
            'autoDetectFolderOffset' => true,
            // Legacy behavior: if multiple routes match, execute them in order.
            // Supported values: chain|first|error_on_ambiguous
            'decisionPolicy' => DecisionPolicy::CHAIN,
            // When true, store a structured trace into Request["__route_trace"].
            'debugTrace' => false,
            // fail_closed|fail_open
            'failureMode' => FailureMode::FAIL_CLOSED,
            // Legacy side-effect: auto-generate/update .htaccess.
            'manageHtaccess' => true,
            ...$options,
        ];

        if (($this->options['manageHtaccess'] ?? true) === true) {
            $this->controlHtaccess($root);
        }
    }

    private function controlHtaccess($root)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? false;
        if (!$scriptName) return;

        $htaccessFile = rtrim($root, "\/") . "/" . trim($this->routeOffset, "\/") . "/.htaccess";
        if (!file_exists($htaccessFile)) {
            $htaccess =
                <<<EOS
            # THIS FILE ARE GENERATE BY <IL4MB/ROUTING> 
            # YOU CAN MODIFY ANY THING BUT MAKE SURE EACH REQUEST ARE POINT TO INDEX.PHP
            RewriteEngine on
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ "/$scriptName" [NC,L,QSA]
            EOS;
            file_put_contents($htaccessFile, $htaccess);
        } else {
            $htaccess = file_get_contents($htaccessFile);
            preg_match("/RewriteRule\s+(.*)\s+\[/im", $htaccess, $matches);
            if (isset($matches[1])) {
                $should = "^(.*)$ \"$scriptName\"";
                if ($matches[1] !== $should) {
                    $htaccess = str_replace($matches[1], $should, $htaccess);
                    file_put_contents($htaccessFile, $htaccess);
                }
            }
        }
    }

    public function setRoutingHook(RoutingHook $hook): void
    {
        $this->engineHook = $hook;
    }

    public function removeInterceptor(Interceptor $interceptor): void
    {
        foreach ($this->interceptors as $key => $existingInterceptor) {
            if ($existingInterceptor === $interceptor) {
                unset($this->interceptors[$key]);
                $this->interceptors = array_values($this->interceptors);
                return;
            }
        }
    }

    public function addInterceptor(Interceptor $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }

    public function addRoute(mixed $obj): void
    {
        if ($obj instanceof Route) {
            $this->addRouteInternal($obj);
        } else {
            $this->addRoutesFromController($obj);
        }
    }

    public function addRouteBy(string $basepath, mixed $obj): void
    {
        if ($obj instanceof Route) {
            $this->addRouteInternal($obj, $basepath);
        } else {
            $this->addRoutesFromController($obj, $basepath);
        }
    }

    private function addRouteInternal(Route $route, string $basepath = ''): void
    {
        $basepath   = trim($basepath, "\/");
        $offsetpath = trim($this->routeOffset, "\/");
        $path = (!empty($offsetpath) ? "/$offsetpath" : "")
            . (!empty($basepath) ? "/$basepath" : "") . "/"
            .  trim($route->path, "\/");

        $normalizeHeaderConstraints = static function (array $headers): array {
            $normalized = [];
            foreach ($headers as $name => $value) {
                $key = strtolower((string)$name);
                $normalized[$key] = $value === null ? null : (string)$value;
            }
            ksort($normalized);
            return $normalized;
        };

        if ($this->options['throwOnDuplicatePath']) {
            $duplicates = array_filter(
                $this->routes,
                fn($existingRoute) => $existingRoute->path === $path
                    && $existingRoute->method === $route->method
                    && ($existingRoute->host ?? null) === ($route->host ?? null)
                    && ($existingRoute->protocol ?? null) === ($route->protocol ?? null)
                    && $normalizeHeaderConstraints($existingRoute->headers ?? []) === $normalizeHeaderConstraints($route->headers ?? [])
            );
            if (count($duplicates) > 0) {
                throw new InvalidArgumentException("Cannot add path \"$route->path\", same path already added in collection.");
            }
        }

        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->onAddRoute($route)) break;
        }

        $this->routes[] = $route->clone([
            "path" => $path,
            "callback" => $route->callback
        ]);

        $this->compiledDirty = true;
    }

    private function ensureCompiled(): void
    {
        if (!$this->compiledDirty && $this->compiledTable !== null) {
            return;
        }

        $routeDefs = [];
        foreach ($this->routes as $i => $r) {
            $id = $this->makeRouteId($r, (int)$i);
            $routeDefs[] = HttpRouteCompiler::compile($r, $id);
        }

        $this->compiledTable = new RouteTable($routeDefs);
        $this->compiledDirty = false;
    }

    private function makeRouteId(Route $route, int $index): string
    {
        $host = !empty($route->host) ? (' host=' . $route->host) : '';
        $protocol = !empty($route->protocol) ? (' proto=' . $route->protocol) : '';
        $priority = (int)($route->priority ?? 0);
        $fallback = (bool)($route->fallback ?? false);
        $flags = ' prio=' . $priority . ($fallback ? ' fallback' : '');

        return $route->method . ' ' . $route->path . $host . $protocol . $flags . ' #' . $index;
    }

    private function addRoutesFromController(mixed $controller, string $basepath = ''): void
    {
        $reflector = new ReflectionClass($controller);
        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes() as $defAttr) {
                if ($defAttr->getName() == Route::class) {
                    $routeInstance = $defAttr->newInstance();
                    $routeInstance->callback = Callback::create($method->getName(), $controller);
                    $this->addRouteInternal($routeInstance, $basepath);
                }
            }
        }
    }

    /**
     * Dispath registered route by request
     */
    public function dispatch(Request $request): Response
    {
        $response = new Response();
        try {
            $request->set("__route_options", [
                "pathOffset" => $this->routeOffset,
                ...$this->options
            ]);

            // Phase 1: normalize request into protocol-agnostic engine context.
            $context = HttpContextFactory::fromRequest($request);
            $request->set('__routing_context', $context);

            // Phase 2: compile routes into a cached RouteTable (expensive, done only when routes change).
            $this->ensureCompiled();

            // Phase 3: run routing decision using the engine (single source of truth).
            $policy = DecisionPolicy::normalize((string)($this->options['decisionPolicy'] ?? ''));
            $failureMode = FailureMode::normalize((string)($this->options['failureMode'] ?? ''));
            $tracer = ($this->options['debugTrace'] ?? false) ? new ArrayTracer() : new NullTracer();

            $engine = new RouterEngine(
                table: $this->compiledTable ?? new RouteTable([]),
                hook: $this->engineHook ?? new NoopHook(),
                tracer: $tracer,
                policy: $policy,
                failureMode: $failureMode,
            );

            $outcome = $engine->route($context);

            if (($this->options['debugTrace'] ?? false) && $tracer instanceof ArrayTracer) {
                $request->set('__route_trace', $tracer->events());
            }

            if (!$outcome->ok) {
                throw $outcome->error;
            }

            $decision = $outcome->decision;
            $capturesById = [];
            foreach ($decision?->candidates ?? [] as $c) {
                $capturesById[$c['route']->id] = $c['captures'] ?? [];
            }

            $matchedRoutes = [];
            foreach ($decision?->selected ?? [] as $selectedDef) {
                /** @var Route $selected */
                $selected = $selectedDef->target;
                $caps = $capturesById[$selectedDef->id] ?? [];
                foreach (($selected->parameters ?? []) as $param) {
                    if ($param?->name !== null && array_key_exists($param->name, $caps)) {
                        $param->value = rawurldecode((string)$caps[$param->name]);
                    }
                }
                $matchedRoutes[] = $selected;
            }

            if ($this->options['debugTrace'] ?? false) {
                $request->set('__route_decision', [
                    'selected' => array_map(fn($r) => $r->id, $decision?->selected ?? []),
                    'reason' => $decision?->reason,
                    'policy' => $policy,
                    'failureMode' => $failureMode,
                ]);
            }

            if (empty($matchedRoutes) && $failureMode !== FailureMode::FAIL_OPEN) {
                throw new Exception('Route not found.', 404);
            }

            $request->set("__routes", $matchedRoutes);
            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->onDispatch($request, $response)) break;
            }
        } catch (Throwable $t) {

            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->onFailed($t, $request, $response)) break;
            }
        }
        return $response;
    }

    public function onAddRoute(Route &$route): bool
    {
        return false;
    }

    public function onBeforeInvoke(Route &$route): bool
    {
        return false;
    }

    public function onInvoke(Route &$route): bool
    {
        return false;
    }

    public function onDispatch(Request &$request, Response &$response): bool
    {
        try {
            /**
             * @var array<Route> $routes
             */
            $routes = $request->get("__routes");
            if ($routes) {
                $this->executeRoutes($routes, 0, $request, $response);
            }
        } catch (Throwable $t) {
            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->onFailed($t, $request, $response)) break;
            }
        }
        return false;
    }

    private function executeRoutes(array $routes, int $index, Request $request, Response $response): void
    {
        if (!isset($routes[$index])) // Route not found
            throw new Exception("Route not found.", 404);

        $route = $routes[$index];
        $next = function () use ($routes, $index, $request, $response) {
            $this->executeRoutes($routes, $index + 1, $request, $response);
        };
        $this->invokeRoute($route, $request, $response, $next);
    }

    private function invokeRoute(Route $route, Request $request, Response $response, Closure $next): void
    {
        MiddlewareExecutor::execute($route, $request);
        $params = $route->parameters;
        foreach ($this->interceptors as $interceptor)
            if ($interceptor->onBeforeInvoke($route)) break;
        $content = $route->callback->__invoke(...[...$params, $request, $response, $next]);
        if ($content !== null)
            $response->setContent($content);

        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->onInvoke($route)) break;
        }
    }


    function onFailed(Throwable $t, Request &$request, Response &$response): bool
    {
        $code = (int)$t->getCode();
        if ($code <= 0) {
            if ($t instanceof NoRouteMatchedException) {
                $code = 404;
            } elseif ($t instanceof AmbiguousRouteException) {
                $code = 500;
            } else {
                $code = 500;
            }
        }
        $response->setCode(Code::fromCode($code) ?? 500);
        if (empty($response->getContent())) {
            $response->setContent("Error: {$t->getCode()}, {$t->getMessage()}");
        }
        return false;
    }

    public function __debugInfo()
    {
        return [
            "routes" => $this->routes
        ];
    }
}
