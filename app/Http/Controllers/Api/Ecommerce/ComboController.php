<?php

namespace App\Http\Controllers\Api\Ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Ecommerce\ComboResource;
use App\Models\Combo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComboController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $combos = Combo::query()
            ->where('is_active', true)
            ->with(['items.product' => fn ($q) => $q->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')])
            ->orderBy('name')
            ->get();

        return ComboResource::collection($combos);
    }
}
