<?php

namespace Il4mb\Routing\Engine\Matchers;

/**
 * @param array<string, string> $captures Named captures (e.g. path params).
 */
final class MatchResult
{
    /**
     * @param array<string, string> $captures
     */
    private function __construct(
        public bool $matched,
        public array $captures = [],
        public int $specificity = 0,
        public ?string $reason = null,
    ) {
    }

    public static function no(?string $reason = null): self
    {
        return new self(false, [], 0, $reason);
    }

    /**
     * @param array<string, string> $captures
     */
    public static function yes(array $captures = [], int $specificity = 0, ?string $reason = null): self
    {
        return new self(true, $captures, $specificity, $reason);
    }
}
