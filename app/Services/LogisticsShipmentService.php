<?php

namespace App\Services;

use App\Models\LogisticsClient;
use App\Models\LogisticsShipment;
use App\Models\ShippingCarrier;
use App\Support\ElSalvadorGeo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LogisticsShipmentService
{
    public function create(array $data): LogisticsShipment
    {
        return DB::transaction(function () use ($data) {
            $client = LogisticsClient::query()
                ->where('is_active', true)
                ->findOrFail((int) $data['logistics_client_id']);

            $carrierId = ! empty($data['shipping_carrier_id'])
                ? (int) $data['shipping_carrier_id']
                : (int) ($client->default_shipping_carrier_id ?? 0);

            if ($carrierId <= 0) {
                throw new InvalidArgumentException('Selecciona una empresa de envío (Sistrack).');
            }

            $carrier = ShippingCarrier::query()
                ->where('is_active', true)
                ->findOrFail($carrierId);

            $collect = round(max(0, (float) ($data['collect_amount'] ?? 0)), 2);
            $costs = $carrier->costsForTotal($collect);
            $serviceCommission = $client->serviceCommissionForCollect($collect);
            $payable = LogisticsShipment::computePayable(
                $collect,
                (float) $costs['shipping_cost'],
                (float) $costs['commission_amount'],
                $serviceCommission,
            );

            $department = $data['department'] ?? null;
            $municipality = ElSalvadorGeo::canonicalizeMunicipality(
                $department,
                $data['municipality'] ?? null,
            );

            return LogisticsShipment::query()->create([
                'number' => $this->nextNumber(),
                'logistics_client_id' => $client->id,
                'shipping_carrier_id' => $carrier->id,
                'shipped_at' => isset($data['shipped_at'])
                    ? Carbon::parse($data['shipped_at'])
                    : now(),
                'status' => LogisticsShipment::STATUS_CONFIRMED,
                'status_changed_at' => now(),
                'recipient_name' => trim((string) $data['recipient_name']),
                'recipient_phone' => filled($data['recipient_phone'] ?? null)
                    ? trim((string) $data['recipient_phone'])
                    : null,
                'recipient_email' => filled($data['recipient_email'] ?? null)
                    ? trim((string) $data['recipient_email'])
                    : null,
                'recipient_address' => trim((string) $data['recipient_address']),
                'department' => $department,
                'municipality' => $municipality,
                'country' => $data['country'] ?? 'El Salvador',
                'description' => trim((string) $data['description']),
                'collect_amount' => $collect,
                'carrier_shipping_cost' => $costs['shipping_cost'],
                'carrier_commission_amount' => $costs['commission_amount'],
                'service_commission' => $serviceCommission,
                'payable_to_client' => $payable,
                'notes' => $data['notes'] ?? null,
                'sistrack_status' => LogisticsShipment::SISTRACK_PENDING,
                'created_by_user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Fields mirrored in the Sistrack order/recipient, with the label shown when asking to sync.
     */
    public const SISTRACK_FIELDS = [
        'recipient_name' => 'Nombre del destinatario',
        'recipient_phone' => 'Teléfono',
        'recipient_email' => 'Correo',
        'recipient_address' => 'Dirección',
        'department' => 'Departamento',
        'municipality' => 'Municipio',
        'description' => 'Descripción',
        'collect_amount' => 'COD a cobrar',
        'logistics_client_id' => 'Empresa (va en la descripción)',
    ];

    /**
     * @return array{shipment: LogisticsShipment, sistrack_changes: list<string>}
     */
    public function update(LogisticsShipment $shipment, array $data): array
    {
        return DB::transaction(function () use ($shipment, $data) {
            $shipment = LogisticsShipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if (! $shipment->canEdit()) {
                throw new InvalidArgumentException('Este envío ya no se puede editar (entregado, devuelto, anulado o liquidado).');
            }

            $client = LogisticsClient::query()->findOrFail((int) $data['logistics_client_id']);
            $carrierId = ! empty($data['shipping_carrier_id'])
                ? (int) $data['shipping_carrier_id']
                : (int) ($client->default_shipping_carrier_id ?: $shipment->shipping_carrier_id);
            if ($carrierId !== (int) $shipment->shipping_carrier_id && $shipment->isSistrackSent()) {
                throw new InvalidArgumentException('No se puede cambiar la empresa de envío de un envío que ya está en Sistrack. Cancélalo y crea uno nuevo.');
            }
            $carrier = ShippingCarrier::query()->findOrFail($carrierId);

            $department = $data['department'] ?? null;
            $collect = round(max(0, (float) ($data['collect_amount'] ?? 0)), 2);

            $shipment->fill([
                'logistics_client_id' => $client->id,
                'shipping_carrier_id' => $carrier->id,
                'shipped_at' => isset($data['shipped_at']) ? Carbon::parse($data['shipped_at']) : $shipment->shipped_at,
                'recipient_name' => trim((string) $data['recipient_name']),
                'recipient_phone' => filled($data['recipient_phone'] ?? null) ? trim((string) $data['recipient_phone']) : null,
                'recipient_email' => filled($data['recipient_email'] ?? null) ? trim((string) $data['recipient_email']) : null,
                'recipient_address' => trim((string) $data['recipient_address']),
                'department' => $department,
                'municipality' => ElSalvadorGeo::canonicalizeMunicipality($department, $data['municipality'] ?? null),
                'country' => $data['country'] ?? $shipment->country ?? 'El Salvador',
                'description' => trim((string) $data['description']),
                'collect_amount' => $collect,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($shipment->isDirty(['collect_amount', 'logistics_client_id', 'shipping_carrier_id'])) {
                $costs = $carrier->costsForTotal($collect);
                $serviceCommission = $client->serviceCommissionForCollect($collect);
                $shipment->fill([
                    'carrier_shipping_cost' => $costs['shipping_cost'],
                    'carrier_commission_amount' => $costs['commission_amount'],
                    'service_commission' => $serviceCommission,
                    'payable_to_client' => LogisticsShipment::computePayable(
                        $collect,
                        (float) $costs['shipping_cost'],
                        (float) $costs['commission_amount'],
                        $serviceCommission,
                    ),
                ]);
            }

            $changes = collect(self::SISTRACK_FIELDS)
                ->filter(fn ($label, $field) => $shipment->isDirty($field))
                ->values()
                ->all();

            $shipment->save();

            return ['shipment' => $shipment->fresh(), 'sistrack_changes' => $changes];
        });
    }

    public function markDelivered(LogisticsShipment $shipment): LogisticsShipment
    {
        if (! $shipment->canMarkDelivered()) {
            throw new RuntimeException('Este envío no se puede marcar como entregado.');
        }

        $shipment->forceFill([
            'status' => LogisticsShipment::STATUS_DELIVERED,
            'status_changed_at' => now(),
        ])->save();

        return $shipment->fresh();
    }

    public function markReturned(LogisticsShipment $shipment): LogisticsShipment
    {
        if (! $shipment->canMarkReturned()) {
            throw new RuntimeException('Este envío no se puede marcar como devolución.');
        }

        $shipment->forceFill([
            'status' => LogisticsShipment::STATUS_RETURNED,
            'status_changed_at' => now(),
            // En devolución no hay COD que devolver a la empresa.
            'payable_to_client' => 0,
        ])->save();

        return $shipment->fresh();
    }

    public function void(LogisticsShipment $shipment): LogisticsShipment
    {
        if ($shipment->isVoided()) {
            throw new InvalidArgumentException('Este envío ya está anulado.');
        }

        if ($shipment->isDelivered()) {
            throw new InvalidArgumentException('No se puede anular un envío ya entregado.');
        }

        if ($shipment->isSettled()) {
            throw new InvalidArgumentException('No se puede anular un envío ya liquidado. Anula primero la liquidación.');
        }

        $shipment->forceFill([
            'status' => LogisticsShipment::STATUS_VOIDED,
            'status_changed_at' => now(),
            'voided_at' => now(),
            'voided_by_user_id' => auth()->id(),
            'payable_to_client' => 0,
        ])->save();

        return $shipment->fresh();
    }

    public function nextNumber(): string
    {
        $prefix = 'ENV-'.now()->format('Ymd').'-';
        $max = LogisticsShipment::query()
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(number, '.(strlen($prefix) + 1).') AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return $prefix.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }
}
