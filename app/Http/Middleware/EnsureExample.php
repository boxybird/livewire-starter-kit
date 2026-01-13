<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExample
{
    public function handle(Request $request, Closure $next): Response
    {
        // Example middleware logic
        return $next($request);
    }
}
