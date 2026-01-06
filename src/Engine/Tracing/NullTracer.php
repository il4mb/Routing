<?php

namespace Il4mb\Routing\Engine\Tracing;

use Il4mb\Routing\Engine\RoutingContext;

final class NullTracer implements Tracer
{
    public function event(string $stage, string $message, array $data = []): void
    {
    }

    public function start(RoutingContext $context): void
    {
    }

    public function finish(): void
    {
    }
}
