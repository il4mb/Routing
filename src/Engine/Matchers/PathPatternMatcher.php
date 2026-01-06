<?php

namespace Il4mb\Routing\Engine\Matchers;

use Il4mb\Routing\Engine\RoutingContext;

/**
 * Path matching with a small, infrastructure-friendly pattern syntax:
 *
 * - Static: /api/v1/users
 * - Named segment: /users/{id}
 * - Named segment with regex: /users/{id:[0-9]+}
 * - Single segment wildcard: /files/*
 * - Greedy wildcard (rest-of-path): /proxy/** or /{rest:**}
 */
final class PathPatternMatcher implements Matcher
{
    /** @var array<string, array{0:string,1:int}> */
    private static array $compileCache = [];

    private string $compiled;
    private int $specificity;

    public function __construct(
        private string $pattern,
        private bool $caseSensitive = true,
    ) {
        [$this->compiled, $this->specificity] = self::compile($pattern, $caseSensitive);
    }

    public function name(): string
    {
        return 'path';
    }

    public function match(RoutingContext $context): MatchResult
    {
        $path = $context->path;
        if ($path === '') {
            $path = '/';
        }

        $matches = [];
        if (!preg_match($this->compiled, $path, $matches)) {
            return MatchResult::no('path mismatch');
        }

        $captures = [];
        foreach ($matches as $key => $value) {
            if (is_string($key) && $value !== '') {
                $captures[$key] = $value;
            }
        }

        return MatchResult::yes($captures, $this->specificity);
    }

    /**
     * @return array{0:string,1:int} regex + specificity
     */
    private static function compile(string $pattern, bool $caseSensitive): array
    {
        $cacheKey = ($caseSensitive ? '1' : '0') . "\0" . $pattern;
        if (isset(self::$compileCache[$cacheKey])) {
            return self::$compileCache[$cacheKey];
        }

        $normalized = '/' . ltrim($pattern, '/');
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        $specificity = 0;
        $segments = array_values(array_filter(explode('/', $normalized), fn($s) => $s !== ''));

        // Special-case root.
        if (count($segments) === 0) {
            $flags = $caseSensitive ? '' : 'i';
            return self::$compileCache[$cacheKey] = ['#^/?$#' . $flags, 10];
        }

        $parts = [];
        foreach ($segments as $seg) {
            if ($seg === '*') {
                $parts[] = '([^/]+)';
                continue;
            }
            if ($seg === '**') {
                $parts[] = '(.*)';
                // Greedy rest-of-path; stop processing.
                break;
            }

            // Legacy greedy capture: {name.*}
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\.\*\}$/', $seg, $m)) {
                $parts[] = '(?P<' . $m[1] . '>.*)';
                continue;
            }

            // Explicit greedy capture: {name:**}
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*):\*\*\}$/', $seg, $m)) {
                $parts[] = '(?P<' . $m[1] . '>.*)';
                continue;
            }

            // Legacy expected list: {name[a,b,c]}
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\[([^\]]*)\]\}$/', $seg, $m)) {
                $name = $m[1];
                $raw = $m[2];
                $values = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
                $alts = array_map(fn($v) => preg_quote($v, '#'), $values);
                $parts[] = '(?P<' . $name . '>' . implode('|', $alts) . ')';
                $specificity += 1;
                continue;
            }

            // Regex-constrained: {name:regex}
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*):(.+)\}$/', $seg, $m)) {
                $name = $m[1];
                $expr = $m[2];
                $parts[] = '(?P<' . $name . '>' . $expr . ')';
                $specificity += 1;
                continue;
            }

            // Basic named segment: {name}
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $seg, $m)) {
                $parts[] = '(?P<' . $m[1] . '>[^/]+)';
                $specificity += 1;
                continue;
            }

            // Static segment.
            $parts[] = preg_quote($seg, '#');
            $specificity += 2;
        }

        $regex = '#^/' . implode('/', $parts) . '/?$#' . ($caseSensitive ? '' : 'i');
        return self::$compileCache[$cacheKey] = [$regex, $specificity];
    }
}
