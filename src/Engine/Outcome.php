<?php

namespace Il4mb\Routing\Engine;

use Throwable;

final class Outcome
{
    private function __construct(
        public bool $ok,
        public ?Decision $decision = null,
        public ?Throwable $error = null,
    ) {
    }

    public static function success(Decision $decision): self
    {
        return new self(true, $decision, null);
    }

    public static function failure(Throwable $t, ?Decision $decision = null): self
    {
        return new self(false, $decision, $t);
    }
}
