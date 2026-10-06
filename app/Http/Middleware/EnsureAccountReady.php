<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out deactivated users and forces a password change before anything else.
 */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Tu sesión terminó. Ingresa de nuevo.']);
        }

        if ($user->must_change_password && ! $request->routeIs('account.*')) {
            return redirect()->route('account.edit')->with('error', 'Cambia tu contraseña para continuar.');
        }

        return $next($request);
    }
}
