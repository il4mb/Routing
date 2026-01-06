<?php

namespace Il4mb\Routing\Http;

final class Method
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const PATCH = 'PATCH';

    /**
     * @return string|null Normalized method or null if unknown.
     */
    public static function tryFrom(?string $method): ?string
    {
        if ($method === null) {
            return null;
        }

        $m = strtoupper($method);
        return in_array($m, [self::GET, self::POST, self::PUT, self::DELETE, self::PATCH], true) ? $m : null;
    }
}
