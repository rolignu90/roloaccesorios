<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreLogisticsClientRequest;
use App\Http\Requests\Logistics\UpdateLogisticsClientRequest;
use App\Models\LogisticsClient;
use App\Models\ShippingCarrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LogisticsClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = LogisticsClient::query()
            ->with('defaultShippingCarrier:id,name')
            ->withCount('shipments')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('logistics.clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('logistics.clients.create', [
            'nextCode' => LogisticsClient::nextCode(),
            'carriers' => $this->carrierOptions(),
        ]);
    }

    public function store(StoreLogisticsClientRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $data['code'] = LogisticsClient::nextCode();
            LogisticsClient::query()->create($data);
        });

        return redirect()
            ->route('logistics.clients.index')
            ->with('success', 'Cliente logístico creado.');
    }

    public function show(LogisticsClient $client): View
    {
        $client->load(['defaultShippingCarrier']);
        $recentShipments = $client->shipments()
            ->with('shippingCarrier:id,name')
            ->latest('shipped_at')
            ->limit(25)
            ->get();

        return view('logistics.clients.show', compact('client', 'recentShipments'));
    }

    public function edit(LogisticsClient $client): View
    {
        return view('logistics.clients.edit', [
            'client' => $client,
            'carriers' => $this->carrierOptions(),
        ]);
    }

    public function update(UpdateLogisticsClientRequest $request, LogisticsClient $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('logistics.clients.show', $client)
            ->with('success', 'Cliente logístico actualizado.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, ShippingCarrier>
     */
    private function carrierOptions()
    {
        return ShippingCarrier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'shipping_cost', 'commission_type', 'commission_value', 'sistrack_enabled']);
    }
}
