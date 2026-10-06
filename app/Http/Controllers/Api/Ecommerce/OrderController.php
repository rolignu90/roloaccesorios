<?php

namespace App\Http\Controllers\Api\Ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\StoreEcommerceOrderRequest;
use App\Http\Resources\Api\Ecommerce\OrderResource;
use App\Services\EcommerceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderController extends Controller
{
    public function __construct(private EcommerceOrderService $orders) {}

    public function store(StoreEcommerceOrderRequest $request): JsonResponse|OrderResource
    {
        try {
            $result = $this->orders->createOrFind($request->validated());
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudo crear el pedido.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        $resource = new OrderResource($result['sale']);

        return $resource->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(string $externalOrderId): OrderResource|JsonResponse
    {
        $sale = $this->orders->findByExternalOrderId($externalOrderId);

        if (! $sale) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        return new OrderResource($sale);
    }

    public function showByNumber(string $number): OrderResource|JsonResponse
    {
        $sale = $this->orders->findByNumber($number);

        if (! $sale) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        return new OrderResource($sale);
    }
}
