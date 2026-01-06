<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

/**
 * Host matching.
 *
 * Supports exact match and leading wildcard: "*.example.com".
 */
final class HostMatcher implements Matcher
{
    public function __construct(private string $hostPattern)
    {
    }

    public function hostPattern(): string
    {
        return $this->hostPattern;
    }

    public function name(): string
    {
        return 'host';
    }

    public function match(RoutingContext $context): MatchResult
    {
        $expected = strtolower($this->hostPattern);
        $actual = strtolower($context->host);

        if ($expected === '') {
            return MatchResult::yes([], 0);
        }

        if ($expected === $actual) {
            return MatchResult::yes([], 2);
        }

        if (str_starts_with($expected, '*.')) {
            $suffix = substr($expected, 1); // keep the dot
            if ($suffix !== '' && str_ends_with($actual, $suffix)) {
                return MatchResult::yes([], 1);
            }
        }

        return MatchResult::no('host mismatch');
    }
}
