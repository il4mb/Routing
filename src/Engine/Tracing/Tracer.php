<?php

namespace Il4mb\Routing\Engine\Tracing;

use Il4mb\Routing\Engine\RoutingContext;

interface Tracer
{
    /**
     * @param array<string, mixed> $data
     */
    public function event(string $stage, string $message, array $data = []): void;

    public function start(RoutingContext $context): void;

    public function finish(): void;
}
