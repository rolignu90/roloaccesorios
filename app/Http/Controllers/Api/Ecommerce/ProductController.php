<?php

namespace App\Http\Controllers\Api\Ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Ecommerce\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    public function show(string $code): ProductResource|JsonResponse
    {
        $product = Product::query()
            ->where('is_active', true)
            ->where('code', $code)
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        return new ProductResource($product);
    }
}
