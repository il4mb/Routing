<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

/**
 * Header matcher.
 *
 * Values are compared case-sensitively by default (good for tokens); callers can normalize.
 */
final class HeaderMatcher implements Matcher
{
    public function __construct(
        private string $name,
        private ?string $equals = null,
    ) {
    }

    public function name(): string
    {
        return 'header:' . strtolower($this->name);
    }

    public function match(RoutingContext $context): MatchResult
    {
        $headers = $context->headers;
        if ($headers === null) {
            return MatchResult::no('no headers in context');
        }

        $val = $headers[$this->name];
        if ($val === null) {
            return MatchResult::no('header missing');
        }

        if ($this->equals === null) {
            return MatchResult::yes([], 1);
        }

        return ((string)$val === $this->equals)
            ? MatchResult::yes([], 2)
            : MatchResult::no('header mismatch');
    }
}
