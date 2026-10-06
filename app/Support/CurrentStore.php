<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The store this device works as, remembered in a long-lived cookie and
 * limited to the stores the logged-in user may operate.
 */
class CurrentStore
{
    public const COOKIE = 'pos_store_id';

    /**
     * Returns the remembered store if the user can access it, or their only accessible store.
     */
    public static function resolve(Request $request): ?Store
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $stores = $user->accessibleStores();

        $id = (int) $request->cookie(self::COOKIE);
        if ($id > 0 && ($store = $stores->firstWhere('id', $id))) {
            return $store;
        }

        if ($stores->count() === 1) {
            self::remember($stores->first());

            return $stores->first();
        }

        return null;
    }

    public static function remember(Store $store): void
    {
        Cookie::queue(Cookie::forever(self::COOKIE, (string) $store->id));
    }
}
