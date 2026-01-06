<?php

namespace Il4mb\Routing\Engine;

use Il4mb\Routing\Engine\Errors\AmbiguousRouteException;
use Il4mb\Routing\Engine\Errors\NoRouteMatchedException;
use Il4mb\Routing\Engine\Hooks\NoopHook;
use Il4mb\Routing\Engine\Hooks\RoutingHook;
use Il4mb\Routing\Engine\Matchers\AttributeMatcher;
use Il4mb\Routing\Engine\Matchers\HeaderMatcher;
use Il4mb\Routing\Engine\Tracing\NullTracer;
use Il4mb\Routing\Engine\Tracing\Tracer;
use Throwable;

/**
 * Deterministic routing pipeline:
 *
 * 1) preRoute hooks (normalize / enrich context)
 * 2) route matching (candidate evaluation)
 * 3) decision resolution (first/chain/strict)
 * 4) postRoute hooks
 */
final class RouterEngine
{
    /** @var array<string, Outcome> */
    private array $decisionCache = [];
    /** @var list<string> */
    private array $decisionCacheOrder = [];
    /** @var list<string> */
    private array $cacheHeaderNames = [];
    private bool $cacheEnabled = false;

    public function __construct(
        private RouteTable $table,
        private RoutingHook $hook = new NoopHook(),
        private Tracer $tracer = new NullTracer(),
        private string $policy = DecisionPolicy::FIRST,
        private string $failureMode = FailureMode::FAIL_CLOSED,
        private bool $cacheDecisions = false,
        private int $cacheSize = 256,
    ) {
        $this->configureCache();
    }

    public function reload(RouteTable $table): void
    {
        // Safe for long-running apps if swapped under a lock.
        $this->table = $table;

        // Routing table changes invalidate prior decisions.
        $this->decisionCache = [];
        $this->decisionCacheOrder = [];
        $this->configureCache();
    }

    private function configureCache(): void
    {
        $this->cacheEnabled = false;
        $this->cacheHeaderNames = [];

        if (!$this->cacheDecisions) {
            return;
        }
        if (!($this->hook instanceof NoopHook)) {
            // preRoute/postRoute hooks can change semantics; keep caching explicit + safe.
            return;
        }
        if (!($this->tracer instanceof NullTracer)) {
            // Cached hits would skip per-route trace events; keep behavior unsurprising.
            return;
        }
        if ($this->cacheSize <= 0) {
            return;
        }

        // Conservative safety: disable decision caching when routes use runtime conditions
        // or matchers that depend on context attributes.
        foreach ($this->table->routes() as $route) {
            if ($route->condition !== null) {
                return;
            }
            foreach ($route->matchers as $matcher) {
                if ($matcher instanceof AttributeMatcher) {
                    return;
                }
            }
        }

        // Include only headers that routes actually reference.
        $headerNames = [];
        foreach ($this->table->routes() as $route) {
            foreach ($route->matchers as $matcher) {
                if ($matcher instanceof HeaderMatcher) {
                    $name = $matcher->name();
                    // name() is "header:<lower>".
                    $headerNames[] = substr($name, strlen('header:'));
                }
            }
        }
        $headerNames = array_values(array_unique(array_filter($headerNames, fn($h) => is_string($h) && $h !== '')));
        sort($headerNames);
        $this->cacheHeaderNames = $headerNames;
        $this->cacheEnabled = true;
    }

    private function cacheKey(RoutingContext $context): string
    {
        $method = strtoupper((string)($context->method ?? ''));
        $host = strtolower((string)$context->host);
        $protocol = strtolower((string)$context->protocol);
        $path = (string)$context->path;

        $parts = [$protocol, $host, $method, $path];

        if (!empty($this->cacheHeaderNames)) {
            $headers = $context->headers;
            foreach ($this->cacheHeaderNames as $h) {
                $parts[] = $h;
                $parts[] = $headers === null ? '' : (string)($headers[$h] ?? '');
            }
        }

        return implode("\0", $parts);
    }

    private function cacheGet(string $key): ?Outcome
    {
        if (!isset($this->decisionCache[$key])) {
            return null;
        }

        // Move to the end (simple LRU).
        $idx = array_search($key, $this->decisionCacheOrder, true);
        if ($idx !== false) {
            array_splice($this->decisionCacheOrder, (int)$idx, 1);
        }
        $this->decisionCacheOrder[] = $key;

        return $this->decisionCache[$key];
    }

    private function cachePut(string $key, Outcome $outcome): void
    {
        $this->decisionCache[$key] = $outcome;

        $idx = array_search($key, $this->decisionCacheOrder, true);
        if ($idx !== false) {
            array_splice($this->decisionCacheOrder, (int)$idx, 1);
        }
        $this->decisionCacheOrder[] = $key;

        while (count($this->decisionCacheOrder) > $this->cacheSize) {
            $oldest = array_shift($this->decisionCacheOrder);
            if ($oldest !== null) {
                unset($this->decisionCache[$oldest]);
            }
        }
    }

    public function route(RoutingContext $context): Outcome
    {
        $this->tracer->start($context);

        $cacheKey = null;

        try {
            $context = $this->hook->preRoute($context);
            $this->tracer->event('pre', 'context prepared');

            if ($this->cacheEnabled) {
                $cacheKey = $this->cacheKey($context);
                $hit = $this->cacheGet($cacheKey);
                if ($hit !== null) {
                    $this->tracer->event('cache', 'decision cache hit');
                    $this->tracer->finish();
                    return $hit;
                }
            }

            $candidates = $this->table->candidates($context->method ?? '', $context->host);
            $this->tracer->event('match', 'candidates loaded', ['count' => count($candidates)]);

            $matched = [];
            foreach ($candidates as $route) {
                if ($route->condition !== null && !($route->condition)($context)) {
                    $this->tracer->event('match', 'condition rejected', ['route' => $route->id]);
                    continue;
                }

                $res = $route->matcher()->match($context);
                if (!$res->matched) {
                    $this->tracer->event('match', 'no match', ['route' => $route->id, 'reason' => $res->reason]);
                    continue;
                }

                $matched[] = [
                    'route' => $route,
                    'captures' => $res->captures,
                    'priority' => $route->priority,
                    'specificity' => $res->specificity,
                ];
            }

            // Deterministic ordering.
            usort($matched, function (array $a, array $b): int {
                if ($a['priority'] !== $b['priority']) {
                    return $b['priority'] <=> $a['priority'];
                }
                if ($a['specificity'] !== $b['specificity']) {
                    return $b['specificity'] <=> $a['specificity'];
                }
                return strcmp($a['route']->id, $b['route']->id);
            });

            $nonFallback = array_values(array_filter($matched, fn($m) => !$m['route']->fallback));
            $fallback = array_values(array_filter($matched, fn($m) => $m['route']->fallback));

            if (count($nonFallback) === 0 && count($fallback) === 0) {
                throw new NoRouteMatchedException('No route matched the context');
            }

            $selected = [];
            $reason = null;

            if (count($nonFallback) > 0) {
                if ($this->policy === DecisionPolicy::ERROR_ON_AMBIGUOUS && count($nonFallback) > 1) {
                    $ids = array_map(fn($m) => $m['route']->id, $nonFallback);
                    throw new AmbiguousRouteException('Ambiguous routes: ' . implode(', ', $ids));
                }

                if ($this->policy === DecisionPolicy::CHAIN) {
                    $selected = array_map(fn($m) => $m['route'], $nonFallback);
                    $reason = 'chained non-fallback routes';
                } else {
                    $selected = [$nonFallback[0]['route']];
                    $reason = 'selected best non-fallback route';
                }
            } else {
                // Only fallbacks matched.
                $selected = [$fallback[0]['route']];
                $reason = 'selected fallback route';
            }

            $decision = new Decision($matched, $selected, $reason);
            $this->hook->postMatch($context, $decision);
            $this->tracer->event('resolve', 'decision made', [
                'selected' => array_map(fn(RouteDefinition $r) => $r->id, $selected),
                'policy' => $this->policy,
            ]);

            $this->hook->postRoute($context, $decision);
            $this->tracer->finish();

            $out = Outcome::success($decision);
            if ($this->cacheEnabled && $cacheKey !== null) {
                $this->cachePut($cacheKey, $out);
            }
            return $out;
        } catch (Throwable $t) {
            $this->hook->onError($context, $t);
            $this->tracer->event('error', 'routing failure', ['type' => get_class($t), 'message' => $t->getMessage()]);
            $this->tracer->finish();

            if ($this->failureMode === FailureMode::FAIL_OPEN) {
                // Fail-open returns an empty decision; caller decides what "open" means.
                $out = Outcome::success(new Decision([], [], 'fail-open'));
                if ($this->cacheEnabled && $cacheKey !== null) {
                    $this->cachePut($cacheKey, $out);
                }
                return $out;
            }

            $out = Outcome::failure($t);
            if ($this->cacheEnabled && $cacheKey !== null) {
                $this->cachePut($cacheKey, $out);
            }
            return $out;
        }
    }
}
