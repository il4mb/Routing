<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

function test_reset_http_env(): void
{
    $_GET = [];
    $_POST = [];
    $_COOKIE = [];
    $_FILES = [];

    // Minimal server defaults used by Request/Url.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? '/tmp';
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    unset($_SERVER['HTTPS']);
}

function test_assert(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

function test_equal(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        $a = var_export($actual, true);
        $e = var_export($expected, true);
        throw new RuntimeException($message . "\nExpected: $e\nActual:   $a");
    }
}

function test_contains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message . "\nNeedle: $needle\nHaystack: $haystack");
    }
}
