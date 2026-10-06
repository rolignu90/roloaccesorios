<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:sales.create,sales.view_all') — passes when the user has any of them.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()?->hasAnyPermission(...$permissions)) {
            abort(403, 'No tienes permiso para esta sección.');
        }

        return $next($request);
    }
}
