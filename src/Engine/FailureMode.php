<?php

namespace Il4mb\Routing\Engine;

final class FailureMode
{
    public const FAIL_CLOSED = 'fail_closed';
    public const FAIL_OPEN = 'fail_open';

    public static function normalize(?string $value): string
    {
        return $value === self::FAIL_OPEN ? self::FAIL_OPEN : self::FAIL_CLOSED;
    }
}
