<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

/**
 * Combine matchers with an AND policy.
 */
final class CompositeMatcher implements Matcher
{
    /**
     * @param list<Matcher> $matchers
     */
    public function __construct(private array $matchers)
    {
    }

    public function name(): string
    {
        return 'composite';
    }

    public function match(RoutingContext $context): MatchResult
    {
        $captures = [];
        $specificity = 0;

        foreach ($this->matchers as $matcher) {
            $res = $matcher->match($context);
            if (!$res->matched) {
                return MatchResult::no($matcher->name() . ': ' . ($res->reason ?? 'no match'));
            }
            $specificity += $res->specificity;
            $captures = array_merge($captures, $res->captures);
        }

        return MatchResult::yes($captures, $specificity);
    }
}
