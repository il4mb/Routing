<?php

namespace Il4mb\Routing\Engine;

use Il4mb\Routing\Engine\Matchers\CompositeMatcher;
use Il4mb\Routing\Engine\Matchers\Matcher;

final class RouteDefinition
{
    /**
     * @param list<Matcher> $matchers
     * @param list<class-string> $middlewares Optional adapter-level middlewares
     */
    public function __construct(
        public string $id,
        public mixed $target,
        public array $matchers,
        public int $priority = 0,
        public bool $fallback = false,
        public ?\Closure $condition = null,
        public array $middlewares = [],
        public array $metadata = [],
    ) {
    }

    public function matcher(): Matcher
    {
        return new CompositeMatcher($this->matchers);
    }
}
