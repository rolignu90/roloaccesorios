<?php

namespace App\Http\Middleware;

use App\Models\Sale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to sales outside the user's scope, for {sale} routes and sale_ids[] bulk actions.
 */
class EnsureSaleVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->hasPermission('sales.view_all')) {
            return $next($request);
        }

        $sale = $request->route('sale');
        if ($sale instanceof Sale && ! $sale->isVisibleTo($user)) {
            abort(403, 'Esta venta no es tuya.');
        }

        $ids = array_filter(array_map('intval', (array) $request->input('sale_ids', [])));
        if ($ids !== []) {
            $visible = Sale::query()->whereIn('id', $ids)->visibleTo($user)->count();
            if ($visible !== count(array_unique($ids))) {
                abort(403, 'Seleccionaste ventas que no son tuyas.');
            }
        }

        return $next($request);
    }
}
