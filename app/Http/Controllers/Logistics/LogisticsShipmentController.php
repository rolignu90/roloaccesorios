<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreLogisticsShipmentRequest;
use App\Models\LogisticsClient;
use App\Models\LogisticsShipment;
use App\Models\ShippingCarrier;
use App\Services\LogisticsShipmentService;
use App\Services\LogisticsSistrackSyncService;
use App\Services\SistrackClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class LogisticsShipmentController extends Controller
{
    public function __construct(
        private LogisticsShipmentService $shipments,
        private LogisticsSistrackSyncService $sistrack,
    ) {}

    public function index(Request $request): View
    {
        $shipments = LogisticsShipment::query()
            ->with(['client:id,code,name', 'shippingCarrier:id,name', 'settlementItems.settlement:id,number,status'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('number', 'like', "%{$term}%")
                        ->orWhere('recipient_name', 'like', "%{$term}%")
                        ->orWhere('recipient_phone', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('client_id'), fn ($q) => $q->where('logistics_client_id', $request->integer('client_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->boolean('pending_sync'), fn ($q) => $q->pendingSistrackStatusSync())
            ->when(in_array($request->input('settled'), ['1', '0'], true), fn ($q) => $q->settled($request->input('settled') === '1'))
            ->latest('shipped_at')
            ->paginate(30)
            ->withQueryString();

        return view('logistics.shipments.index', [
            'shipments' => $shipments,
            'clients' => LogisticsClient::query()->orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => LogisticsShipment::STATUS_LABELS,
        ]);
    }

    public function create(Request $request): View
    {
        $clients = LogisticsClient::query()
            ->where('is_active', true)
            ->with('defaultShippingCarrier:id,name')
            ->orderBy('name')
            ->get();

        return view('logistics.shipments.create', [
            'clients' => $clients,
            'carriers' => ShippingCarrier::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedClientId' => $request->integer('client_id') ?: null,
            'nextNumber' => $this->shipments->nextNumber(),
        ]);
    }

    public function store(StoreLogisticsShipmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $send = (bool) ($data['send_to_sistrack'] ?? false);
        unset($data['send_to_sistrack']);

        $shipment = $this->shipments->create($data);

        $message = "Envío {$shipment->number} creado.";
        if ($send) {
            try {
                $this->sistrack->sendOne($shipment);
                $message .= ' Enviado a Sistrack.';
            } catch (Throwable $e) {
                return redirect()
                    ->route('logistics.shipments.show', $shipment)
                    ->with('error', $message.' Error Sistrack: '.$e->getMessage());
            }
        }

        return redirect()
            ->route('logistics.shipments.show', $shipment)
            ->with('success', $message);
    }

    public function show(LogisticsShipment $shipment): View
    {
        $shipment->load(['client', 'shippingCarrier', 'settlementItems.settlement', 'createdBy:id,name', 'voidedBy:id,name']);

        return view('logistics.shipments.show', compact('shipment'));
    }

    public function edit(LogisticsShipment $shipment): View|RedirectResponse
    {
        if (! $shipment->canEdit()) {
            return redirect()->route('logistics.shipments.show', $shipment)
                ->with('error', 'Este envío ya no se puede editar (entregado, devuelto, anulado o liquidado).');
        }

        return view('logistics.shipments.edit', [
            'shipment' => $shipment->load('shippingCarrier'),
            'clients' => LogisticsClient::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $shipment->logistics_client_id))
                ->with('defaultShippingCarrier:id,name')
                ->orderBy('name')
                ->get(),
            'carriers' => ShippingCarrier::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $shipment->shipping_carrier_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(StoreLogisticsShipmentRequest $request, LogisticsShipment $shipment): RedirectResponse
    {
        $data = $request->validated();
        unset($data['send_to_sistrack']);

        try {
            $result = $this->shipments->update($shipment, $data);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $shipment = $result['shipment'];
        $message = "Envío {$shipment->number} actualizado.";
        $show = redirect()->route('logistics.shipments.show', $shipment);

        if (! $shipment->hasSistrackLabel()) {
            return $show->with('success', $message);
        }

        if (! $request->boolean('update_sistrack')) {
            return $show->with('success', $message.($result['sistrack_changes'] !== []
                ? ' Sistrack no se modificó (cambiaste: '.implode(', ', $result['sistrack_changes']).').'
                : ''));
        }

        if ($result['sistrack_changes'] === []) {
            return $show->with('success', $message.' No cambiaste datos que estén en Sistrack; no hubo nada que actualizar allá.');
        }

        try {
            $sync = $this->sistrack->updateInSistrack($shipment);
        } catch (Throwable $e) {
            return $show->with('error', $message.' Error al actualizar Sistrack: '.$e->getMessage());
        }

        if ($sync['warnings'] !== []) {
            return $show->with('error', $message.' Sistrack actualizado con avisos: '.implode(' ', $sync['warnings']));
        }

        return $show->with('success', $message.' También se actualizó en Sistrack (misma guía): '.implode(', ', $result['sistrack_changes']).'.');
    }

    public function sendToSistrack(LogisticsShipment $shipment): RedirectResponse
    {
        try {
            $this->sistrack->sendOne($shipment);
        } catch (Throwable $e) {
            return back()->with('error', 'Sistrack: '.$e->getMessage());
        }

        return back()->with('success', 'Enviado a Sistrack.');
    }

    public function resendToSistrack(LogisticsShipment $shipment): RedirectResponse
    {
        try {
            $this->sistrack->resendOne($shipment);
        } catch (Throwable $e) {
            return back()->with('error', 'Sistrack: '.$e->getMessage());
        }

        return back()->with('success', 'Reenviado a Sistrack.');
    }

    public function syncSistrackStatus(LogisticsShipment $shipment): RedirectResponse
    {
        try {
            $result = $this->sistrack->syncOneStatus($shipment);
        } catch (Throwable $e) {
            return back()->with('error', 'Sync: '.$e->getMessage());
        }

        return back()->with(
            'success',
            $result['changed']
                ? 'Estado actualizado a '.$result['shipment']->statusLabel().'.'
                : 'Sin cambios de estado.'
        );
    }

    public function sistrackLabel(Request $request, LogisticsShipment $shipment): Response|RedirectResponse
    {
        return $this->labelResponse($request, collect([$shipment]), route('logistics.shipments.show', $shipment));
    }

    public function sistrackLabels(Request $request): Response|RedirectResponse
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->unique();
        if ($ids->count() > SistrackClient::LABEL_MAX_PER_BATCH) {
            return redirect()->route('logistics.shipments.index')
                ->with('error', 'Sistrack imprime máximo '.SistrackClient::LABEL_MAX_PER_BATCH.' etiquetas por vez.');
        }

        $shipments = LogisticsShipment::query()->whereIn('id', $ids)->orderBy('number')->get();

        return $this->labelResponse($request, $shipments, route('logistics.shipments.index'));
    }

    private function labelResponse(Request $request, $shipments, string $fallbackUrl): Response|RedirectResponse
    {
        $size = (string) $request->input('size', $request->cookie('sistrack_label_size', SistrackClient::LABEL_DEFAULT_SIZE));
        if (! array_key_exists($size, SistrackClient::LABEL_SIZES)) {
            $size = SistrackClient::LABEL_DEFAULT_SIZE;
        }

        try {
            $html = $this->sistrack->labelsHtml($shipments, $size);
        } catch (Throwable $e) {
            return redirect()->to($fallbackUrl)->with('error', 'Etiqueta Sistrack: '.$e->getMessage());
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none'",
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->withCookie(cookie()->forever('sistrack_label_size', $size));
    }

    public function sendManyToSistrack(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->all();
        $rows = LogisticsShipment::query()->whereIn('id', $ids)->get();
        $result = $this->sistrack->sendMany($rows);

        return back()->with(
            'success',
            'Sistrack: '.count($result['sent']).' enviados, '
            .count($result['failed']).' fallidos, '
            .count($result['skipped']).' omitidos.'
        );
    }

    public function syncManySistrackStatus(Request $request): RedirectResponse
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->all();
        $query = LogisticsShipment::query();
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->pendingSistrackStatusSync()->limit(100);
        }
        $result = $this->sistrack->syncStatuses($query->get());

        return back()->with(
            'success',
            'Sync: '.count($result['updated']).' actualizados, '
            .count($result['unchanged']).' sin cambio, '
            .count($result['failed']).' fallidos.'
        );
    }

    public function markDelivered(LogisticsShipment $shipment): RedirectResponse
    {
        try {
            $this->shipments->markDelivered($shipment);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marcado como entregado.');
    }

    public function markReturned(LogisticsShipment $shipment): RedirectResponse
    {
        try {
            $this->shipments->markReturned($shipment);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marcado como devolución.');
    }

    public function void(LogisticsShipment $shipment): RedirectResponse
    {
        try {
            $this->shipments->void($shipment);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Envío anulado.');
    }
}
