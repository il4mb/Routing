<?php

namespace Il4mb\Routing\Binding;

use ReflectionParameter;

interface ParameterResolver
{
    /**
     * @param array<string, mixed> $captures Named captures from the route match.
     * @param list<mixed> $runtimeArgs Arguments passed to the callback (RouteParam list, Request, Response, next, ...).
     */
    public function resolve(ReflectionParameter $parameter, array $captures, array $runtimeArgs): ParameterResolution;
}
