<?php

namespace Il4mb\Routing\Engine\Hooks;

use Il4mb\Routing\Engine\Decision;
use Il4mb\Routing\Engine\RoutingContext;
use Throwable;

interface RoutingHook
{
    public function preRoute(RoutingContext $context): RoutingContext;

    public function postMatch(RoutingContext $context, Decision $decision): void;

    public function postRoute(RoutingContext $context, Decision $decision): void;

    public function onError(RoutingContext $context, Throwable $t): void;
}
