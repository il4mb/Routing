<?php

namespace Il4mb\Routing\Middlewares;

use Closure;
use Il4mb\Routing\Http\Request;

interface Middleware
{
    public function handle(Request $request, Closure $next);
}
