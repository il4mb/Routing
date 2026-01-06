<?php

namespace Il4mb\Routing\Binding;

final class ParameterResolution
{
    public bool $matched;
    public mixed $value;
    public ?string $reason;

    private function __construct(bool $matched, mixed $value = null, ?string $reason = null)
    {
        $this->matched = $matched;
        $this->value = $value;
        $this->reason = $reason;
    }

    public static function matched(mixed $value): self
    {
        return new self(true, $value, null);
    }

    public static function notMatched(?string $reason = null): self
    {
        return new self(false, null, $reason);
    }
}
