<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

interface Matcher
{
    public function name(): string;

    public function match(RoutingContext $context): MatchResult;
}
