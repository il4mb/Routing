<?php

namespace Il4mb\Routing\Engine\Tracing;

use Il4mb\Routing\Engine\RoutingContext;

final class ArrayTracer implements Tracer
{
    /**
     * @var list<array{stage:string,message:string,data:array<string,mixed>,ts:float}>
     */
    private array $events = [];

    public function start(RoutingContext $context): void
    {
        $this->event('start', 'routing start', [
            'protocol' => $context->protocol,
            'host' => $context->host,
            'path' => $context->path,
            'method' => $context->method,
        ]);
    }

    public function finish(): void
    {
        $this->event('finish', 'routing finish');
    }

    public function event(string $stage, string $message, array $data = []): void
    {
        $this->events[] = [
            'stage' => $stage,
            'message' => $message,
            'data' => $data,
            'ts' => microtime(true),
        ];
    }

    /**
     * @return list<array{stage:string,message:string,data:array<string,mixed>,ts:float}>
     */
    public function events(): array
    {
        return $this->events;
    }
}
