<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

final class MethodMatcher implements Matcher
{
    public function __construct(private string $method)
    {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function name(): string
    {
        return 'method';
    }

    public function match(RoutingContext $context): MatchResult
    {
        $expected = strtoupper($this->method);
        $actual = strtoupper($context->method ?? '');

        if ($expected === '' || $expected === '*') {
            return MatchResult::yes([], 0);
        }

        return $expected === $actual
            ? MatchResult::yes([], 1)
            : MatchResult::no('method mismatch');
    }
}
