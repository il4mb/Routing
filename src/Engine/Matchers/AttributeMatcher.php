<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

/**
 * Match a context attribute by strict equality.
 */
final class AttributeMatcher implements Matcher
{
    public function __construct(
        private string $key,
        private mixed $equals,
    ) {
    }

    public function name(): string
    {
        return 'attr:' . $this->key;
    }

    public function match(RoutingContext $context): MatchResult
    {
        $val = $context->getAttribute($this->key, null);
        return $val === $this->equals
            ? MatchResult::yes([], 1)
            : MatchResult::no('attribute mismatch');
    }
}
