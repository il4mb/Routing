<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

final class ProtocolMatcher implements Matcher
{
    public function __construct(private string $protocol)
    {
    }

    public function name(): string
    {
        return 'protocol';
    }

    public function match(RoutingContext $context): MatchResult
    {
        $expected = strtolower($this->protocol);
        $actual = strtolower($context->protocol);

        if ($expected === '' || $expected === '*') {
            return MatchResult::yes([], 0);
        }

        return $expected === $actual
            ? MatchResult::yes([], 1)
            : MatchResult::no('protocol mismatch');
    }
}
