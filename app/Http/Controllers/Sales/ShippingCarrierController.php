<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreShippingCarrierRequest;
use App\Http\Requests\Sales\UpdateShippingCarrierRequest;
use App\Models\ShippingCarrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingCarrierController extends Controller
{
    public function index(Request $request): View
    {
        $carriers = ShippingCarrier::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('sales.shipping-carriers.index', compact('carriers'));
    }

    public function create(): View
    {
        return view('sales.shipping-carriers.create');
    }

    public function store(StoreShippingCarrierRequest $request): RedirectResponse
    {
        ShippingCarrier::query()->create($request->validated());

        return redirect()
            ->route('sales.shipping-carriers.index')
            ->with('success', 'Empresa de envío creada.');
    }

    public function edit(ShippingCarrier $shippingCarrier): View
    {
        return view('sales.shipping-carriers.edit', [
            'carrier' => $shippingCarrier,
        ]);
    }

    public function update(UpdateShippingCarrierRequest $request, ShippingCarrier $shippingCarrier): RedirectResponse
    {
        $shippingCarrier->update($request->validated());

        return redirect()
            ->route('sales.shipping-carriers.index')
            ->with('success', 'Empresa de envío actualizada.');
    }

    public function destroy(ShippingCarrier $shippingCarrier): RedirectResponse
    {
        if ($shippingCarrier->sales()->exists()) {
            $shippingCarrier->update(['is_active' => false]);

            return redirect()
                ->route('sales.shipping-carriers.index')
                ->with('success', 'La empresa tiene ventas asociadas; se marcó como inactiva.');
        }

        $shippingCarrier->delete();

        return redirect()
            ->route('sales.shipping-carriers.index')
            ->with('success', 'Empresa de envío eliminada.');
    }
}
