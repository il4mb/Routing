<?php

namespace Il4mb\Routing\Middlewares;

use Closure;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;

interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
