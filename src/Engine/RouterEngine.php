<?php

namespace Il4mb\Routing\Engine;

use Il4mb\Routing\Engine\Errors\AmbiguousRouteException;
use Il4mb\Routing\Engine\Errors\NoRouteMatchedException;
use Il4mb\Routing\Engine\Hooks\NoopHook;
use Il4mb\Routing\Engine\Hooks\RoutingHook;
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
    public function __construct(
        private RouteTable $table,
        private RoutingHook $hook = new NoopHook(),
        private Tracer $tracer = new NullTracer(),
        private string $policy = DecisionPolicy::FIRST,
        private string $failureMode = FailureMode::FAIL_CLOSED,
    ) {
    }

    public function reload(RouteTable $table): void
    {
        // Safe for long-running apps if swapped under a lock.
        $this->table = $table;
    }

    public function route(RoutingContext $context): Outcome
    {
        $this->tracer->start($context);

        try {
            $context = $this->hook->preRoute($context);
            $this->tracer->event('pre', 'context prepared');

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

            return Outcome::success($decision);
        } catch (Throwable $t) {
            $this->hook->onError($context, $t);
            $this->tracer->event('error', 'routing failure', ['type' => get_class($t), 'message' => $t->getMessage()]);
            $this->tracer->finish();

            if ($this->failureMode === FailureMode::FAIL_OPEN) {
                // Fail-open returns an empty decision; caller decides what "open" means.
                return Outcome::success(new Decision([], [], 'fail-open'));
            }

            return Outcome::failure($t);
        }
    }
}
