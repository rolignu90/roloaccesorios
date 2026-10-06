<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEcommerceApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('ecommerce.api_token', '');

        if ($configured === '') {
            return response()->json([
                'message' => 'API e-commerce no configurada (ECOMMERCE_API_TOKEN).',
            ], 503);
        }

        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        if (! hash_equals($configured, $matches[1])) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
