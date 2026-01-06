<?php

namespace Il4mb\Routing\Engine;

final class DecisionPolicy
{
    public const FIRST = 'first';
    public const CHAIN = 'chain';
    public const ERROR_ON_AMBIGUOUS = 'error_on_ambiguous';

    public static function normalize(?string $value): string
    {
        if ($value === self::FIRST || $value === self::ERROR_ON_AMBIGUOUS) {
            return $value;
        }
        return self::CHAIN;
    }
}
