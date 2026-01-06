<?php

namespace Il4mb\Routing\Engine;

/**
 * Deterministic explanation of how routing was decided.
 */
final class Decision
{
    /**
     * @param list<array{route: RouteDefinition, captures: array<string,string>, priority:int, specificity:int}> $candidates
     * @param list<RouteDefinition> $selected
     */
    public function __construct(
        public array $candidates,
        public array $selected,
        public ?string $reason = null,
    ) {
    }
}
