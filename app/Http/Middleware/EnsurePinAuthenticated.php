<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePinAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('pin_authenticated')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
