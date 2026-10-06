<?php

namespace App\Services;

use App\Models\LogisticsShipment;
use App\Support\ElSalvadorGeo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LogisticsSistrackSyncService
{
    public function __construct(
        private SistrackClient $client,
        private LogisticsShipmentService $shipments,
    ) {}

    /**
     * @param  Collection<int, LogisticsShipment>|iterable<LogisticsShipment>  $shipments
     * @return array{
     *   sent: list<LogisticsShipment>,
     *   failed: list<array{shipment: LogisticsShipment, error: string}>,
     *   skipped: list<array{shipment: LogisticsShipment, reason: string}>
     * }
     */
    public function sendMany(iterable $shipments): array
    {
        $sent = [];
        $failed = [];
        $skipped = [];

        foreach ($shipments as $shipment) {
            if ($shipment->sistrack_status === LogisticsShipment::SISTRACK_SENT) {
                $skipped[] = ['shipment' => $shipment, 'reason' => 'Ya enviado a Sistrack'];

                continue;
            }
            if (! $shipment->canSendToSistrack()) {
                $skipped[] = ['shipment' => $shipment, 'reason' => 'La empresa de envío no tiene Sistrack o el envío no aplica'];

                continue;
            }

            try {
                $sent[] = $this->sendOne($shipment);
            } catch (Throwable $e) {
                $failed[] = ['shipment' => $shipment->fresh(), 'error' => $e->getMessage()];
            }
        }

        return compact('sent', 'failed', 'skipped');
    }

    public function resendOne(LogisticsShipment $shipment): LogisticsShipment
    {
        if (! $shipment->canResendToSistrack()) {
            throw new RuntimeException('Este envío no se puede reenviar a Sistrack.');
        }

        $shipment->forceFill([
            'sistrack_status' => LogisticsShipment::SISTRACK_PENDING,
            'sistrack_external_id' => null,
            'sistrack_order_id' => null,
            'sistrack_shipping_status' => null,
            'sistrack_status_synced_at' => null,
            'sistrack_last_error' => null,
        ])->save();

        return $this->sendOne($shipment->fresh(), force: true);
    }

    public function sendOne(LogisticsShipment $shipment, bool $force = false): LogisticsShipment
    {
        if (! $force && $shipment->sistrack_status === LogisticsShipment::SISTRACK_SENT && filled($shipment->sistrack_external_id)) {
            return $shipment;
        }

        if ($force) {
            if (! $shipment->isEligibleForSistrackPush()) {
                throw new RuntimeException('Este envío no se puede reenviar a Sistrack (revisa empresa de envío y credenciales).');
            }
        } elseif (! $shipment->canSendToSistrack()) {
            throw new RuntimeException('Este envío no se puede enviar a Sistrack (revisa empresa de envío y credenciales).');
        }

        $shipment->loadMissing(['client', 'shippingCarrier']);
        $carrier = $shipment->shippingCarrier;
        if (! $carrier) {
            throw new RuntimeException('El envío no tiene empresa de envío.');
        }

        $shipment->forceFill([
            'sistrack_last_attempt_at' => now(),
            'sistrack_last_error' => null,
        ])->save();

        try {
            $this->client->useCarrier($carrier)->login();

            if (! $force) {
                $existing = $this->client->findBestOrderByCommerceId($shipment->number);
                if ($existing) {
                    $shipment->forceFill([
                        'sistrack_status' => LogisticsShipment::SISTRACK_SENT,
                        'sistrack_external_id' => (string) $existing['id'],
                        'sistrack_order_id' => $shipment->number,
                        'sistrack_shipping_status' => $existing['shipping_status'] !== null
                            ? (string) $existing['shipping_status']
                            : $shipment->sistrack_shipping_status,
                        'sistrack_last_error' => null,
                        'sistrack_last_attempt_at' => now(),
                    ])->save();

                    return $shipment->fresh();
                }
            }

            $recipientPayload = $this->recipientPayload($shipment);
            $recipientId = $shipment->sistrack_recipient_id
                ?: $this->client->searchRecipientId(
                    $shipment->recipient_phone ?: $shipment->recipient_name,
                    $shipment->recipient_name,
                    $shipment->recipient_phone,
                );

            if ($recipientId) {
                $this->client->updateRecipient((string) $recipientId, $recipientPayload);
            } else {
                $recipientId = $this->client->createRecipient($recipientPayload)['id'];
            }

            $order = $this->client->createOrder($this->orderPayload($shipment, (string) $recipientId));

            $createdId = (string) $order['id'];
            if (! $force) {
                $best = $this->client->findBestOrderByCommerceId($shipment->number);
                if ($best) {
                    $createdId = (string) $best['id'];
                }
            }

            $shipment->forceFill([
                'sistrack_status' => LogisticsShipment::SISTRACK_SENT,
                'sistrack_external_id' => $createdId,
                'sistrack_order_id' => $shipment->number,
                'sistrack_recipient_id' => (string) $recipientId,
                'sistrack_last_error' => null,
                'sistrack_last_attempt_at' => now(),
            ])->save();

            return $shipment->fresh();
        } catch (Throwable $e) {
            $shipment->forceFill([
                'sistrack_status' => LogisticsShipment::SISTRACK_FAILED,
                'sistrack_last_error' => Str::limit($e->getMessage(), 2000),
                'sistrack_last_attempt_at' => now(),
            ])->save();

            throw $e;
        }
    }

    /**
     * Pushes edited recipient + order data to the existing Sistrack order (no new guide).
     *
     * @return array{shipment: LogisticsShipment, warnings: list<string>}
     */
    public function updateInSistrack(LogisticsShipment $shipment): array
    {
        if (! $shipment->hasSistrackLabel()) {
            throw new RuntimeException('Este envío no está en Sistrack.');
        }

        $shipment->loadMissing(['client', 'shippingCarrier']);
        $carrier = $shipment->shippingCarrier;
        if (! $carrier?->supportsSistrack()) {
            throw new RuntimeException('La empresa de envío no tiene Sistrack.');
        }

        $this->client->useCarrier($carrier)->login();
        $externalId = (string) $shipment->sistrack_external_id;
        $recipientPayload = $this->recipientPayload($shipment);

        $recipientId = $shipment->sistrack_recipient_id;
        if (! filled($recipientId)) {
            $label = (string) ($this->client->getOrder($externalId)['fields']['Recipient'] ?? '');
            $recipientId = preg_match('/^\s*(\d+)/', $label, $m) ? $m[1] : null;
        }
        if (! filled($recipientId)) {
            throw new RuntimeException('No se encontró el destinatario de la orden en Sistrack.');
        }

        $this->client->updateRecipient((string) $recipientId, $recipientPayload);

        $order = $this->orderPayload($shipment, (string) $recipientId);
        $result = $this->client->updateOrder($externalId, [
            'description' => $order['description'],
            'declared_value' => $order['declared_value'],
            'use_recipient_address' => 1,
        ]);

        $warnings = [];
        if (($result['fields']['payment_type'] ?? null) && $result['fields']['payment_type'] !== $order['payment_type']) {
            $warnings[] = 'Sistrack no permite cambiar el tipo de pago ('.$result['fields']['payment_type'].' → '.$order['payment_type'].'). Si cambiaste entre COD y prepagado, usa «Reenviar a Sistrack».';
        }

        $orderAddress = $result['resource']['address_line_1_order'] ?? null;
        $orderCity = $result['resource']['city_order'] ?? null;
        $sameText = fn ($a, $b) => Str::lower(trim((string) $a)) === Str::lower(trim((string) $b));
        if ($orderAddress !== null && (! $sameText($orderAddress, $recipientPayload['address_line_1'] ?? '') || ! $sameText($orderCity, $recipientPayload['city'] ?? ''))) {
            $warnings[] = 'El destinatario se actualizó, pero la orden en Sistrack conserva la dirección anterior ('.$orderAddress.', '.$orderCity.'). Corrígela en Sistrack o usa «Reenviar a Sistrack».';
        }

        $shipment->forceFill([
            'sistrack_recipient_id' => (string) $recipientId,
            'sistrack_last_attempt_at' => now(),
            'sistrack_last_error' => null,
        ])->save();

        return ['shipment' => $shipment->fresh(), 'warnings' => $warnings];
    }

    /**
     * @param  Collection<int, LogisticsShipment>  $shipments
     */
    public function labelsHtml(Collection $shipments, string $size): string
    {
        $printable = new EloquentCollection($shipments->filter(fn (LogisticsShipment $s) => $s->hasSistrackLabel())->values()->all());
        if ($printable->isEmpty()) {
            throw new RuntimeException('Ninguno de los envíos seleccionados está en Sistrack todavía.');
        }

        $printable->loadMissing('shippingCarrier');
        $carriers = $printable->pluck('shipping_carrier_id')->unique();
        if ($carriers->count() > 1) {
            throw new RuntimeException('Selecciona envíos de una sola empresa de envío: cada una tiene su propia cuenta Sistrack.');
        }

        $carrier = $printable->first()->shippingCarrier;
        if (! $carrier?->supportsSistrack()) {
            throw new RuntimeException('La empresa de envío no tiene Sistrack.');
        }

        return $this->client->useCarrier($carrier)->labelsHtml(
            $printable->pluck('sistrack_external_id')->all(),
            $size,
        );
    }

    /**
     * @param  Collection<int, LogisticsShipment>|iterable<LogisticsShipment>  $shipments
     * @return array{
     *   updated: list<LogisticsShipment>,
     *   unchanged: list<LogisticsShipment>,
     *   failed: list<array{shipment: LogisticsShipment, error: string}>,
     *   skipped: list<array{shipment: LogisticsShipment, reason: string}>
     * }
     */
    public function syncStatuses(iterable $shipments): array
    {
        $updated = [];
        $unchanged = [];
        $failed = [];
        $skipped = [];

        /** @var array<int, list<LogisticsShipment>> $byCarrier */
        $byCarrier = [];
        foreach ($shipments as $shipment) {
            if (! $shipment->canSyncSistrackStatus()) {
                $skipped[] = ['shipment' => $shipment, 'reason' => 'No enviado a Sistrack o anulado'];

                continue;
            }
            $carrierId = (int) ($shipment->shipping_carrier_id ?? 0);
            $byCarrier[$carrierId][] = $shipment;
        }

        foreach ($byCarrier as $carrierShipments) {
            $first = $carrierShipments[0];
            $first->loadMissing('shippingCarrier');
            $carrier = $first->shippingCarrier;
            if (! $carrier?->supportsSistrack()) {
                foreach ($carrierShipments as $shipment) {
                    $skipped[] = ['shipment' => $shipment, 'reason' => 'Empresa sin Sistrack'];
                }

                continue;
            }

            try {
                $this->client->useCarrier($carrier)->login();
            } catch (Throwable $e) {
                foreach ($carrierShipments as $shipment) {
                    $failed[] = ['shipment' => $shipment, 'error' => $e->getMessage()];
                }

                continue;
            }

            foreach ($carrierShipments as $shipment) {
                try {
                    $result = $this->syncOneStatus($shipment, ensureLogin: false);
                    if ($result['changed']) {
                        $updated[] = $result['shipment'];
                    } else {
                        $unchanged[] = $result['shipment'];
                    }
                } catch (Throwable $e) {
                    $failed[] = ['shipment' => $shipment->fresh(), 'error' => $e->getMessage()];
                }
            }
        }

        return compact('updated', 'unchanged', 'failed', 'skipped');
    }

    /**
     * @return array{shipment: LogisticsShipment, changed: bool}
     */
    public function syncOneStatus(LogisticsShipment $shipment, bool $ensureLogin = true): array
    {
        if (! $shipment->canSyncSistrackStatus()) {
            throw new RuntimeException('Este envío no se puede sincronizar con Sistrack.');
        }

        $shipment->loadMissing('shippingCarrier');
        $carrier = $shipment->shippingCarrier;
        if (! $carrier?->supportsSistrack()) {
            throw new RuntimeException('La empresa de envío no tiene Sistrack.');
        }

        if ($ensureLogin) {
            $this->client->useCarrier($carrier)->login();
        }

        $resolved = $this->resolveSistrackOrder($shipment);
        $order = $resolved['order'];
        $externalId = (string) $resolved['id'];

        $rawStatus = $order['fields']['shipping_status']
            ?? $order['fields']['shipping_status_label']
            ?? null;

        $mapped = $this->mapShippingStatus($rawStatus);
        $rawStored = $rawStatus === null ? null : (string) $rawStatus;

        $shipment->forceFill([
            'sistrack_external_id' => $externalId,
            'sistrack_order_id' => $shipment->number,
            'sistrack_shipping_status' => $rawStored,
            'sistrack_status_synced_at' => now(),
        ]);

        $changed = false;
        if ($mapped && $shipment->canProgressToStatus($mapped) && $shipment->status !== $mapped) {
            if ($mapped === LogisticsShipment::STATUS_RETURNED) {
                $shipment->save();
                $this->shipments->markReturned($shipment->fresh());
                $changed = true;

                return ['shipment' => $shipment->fresh(), 'changed' => $changed];
            }

            if ($mapped === LogisticsShipment::STATUS_DELIVERED) {
                $shipment->save();
                $this->shipments->markDelivered($shipment->fresh());
                $changed = true;

                return ['shipment' => $shipment->fresh(), 'changed' => $changed];
            }

            $shipment->status = $mapped;
            $shipment->status_changed_at = now();
            $changed = true;
        }

        $shipment->save();

        return ['shipment' => $shipment->fresh(), 'changed' => $changed];
    }

    /**
     * @return array{id: string, order: array{id: int|string, fields: array<string, mixed>}}
     */
    private function resolveSistrackOrder(LogisticsShipment $shipment): array
    {
        $candidates = [];

        if (filled($shipment->sistrack_external_id)) {
            try {
                $order = $this->client->getOrder($shipment->sistrack_external_id);
                $orderId = (string) ($order['fields']['order_id'] ?? '');
                if ($orderId === '' || $orderId === (string) $shipment->number) {
                    $candidates[] = [
                        'id' => (string) $order['id'],
                        'shipping_status' => $order['fields']['shipping_status']
                            ?? $order['fields']['shipping_status_label']
                            ?? null,
                        'order' => $order,
                    ];
                }
            } catch (Throwable) {
                // ID inválido: resolver por Id Comercio.
            }
        }

        foreach ($this->client->findOrdersByCommerceId((string) $shipment->number) as $match) {
            $id = (string) $match['id'];
            if (collect($candidates)->contains(fn ($c) => $c['id'] === $id)) {
                continue;
            }
            try {
                $order = $this->client->getOrder($id);
            } catch (Throwable) {
                continue;
            }
            $candidates[] = [
                'id' => $id,
                'shipping_status' => $match['shipping_status']
                    ?? $order['fields']['shipping_status']
                    ?? $order['fields']['shipping_status_label']
                    ?? null,
                'order' => $order,
            ];
        }

        if ($candidates === []) {
            throw new RuntimeException(
                'No se encontró orden Sistrack para '.$shipment->number
                .(filled($shipment->sistrack_external_id) ? ' (ID '.$shipment->sistrack_external_id.')' : '').'.'
            );
        }

        usort($candidates, function (array $a, array $b) {
            $rankA = $this->client->shippingStatusRank($a['shipping_status'] ?? null);
            $rankB = $this->client->shippingStatusRank($b['shipping_status'] ?? null);
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        $best = $candidates[0];

        return [
            'id' => $best['id'],
            'order' => $best['order'],
        ];
    }

    public function mapShippingStatus(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return match ((int) $raw) {
                1 => LogisticsShipment::STATUS_CONFIRMED,
                2, 3, 4 => LogisticsShipment::STATUS_IN_TRANSIT,
                8 => LogisticsShipment::STATUS_DELIVERED,
                9 => LogisticsShipment::STATUS_RETURNED,
                default => null,
            };
        }

        $normalized = Str::upper(trim((string) $raw));
        $normalized = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $normalized);

        return match ($normalized) {
            'CREADO' => LogisticsShipment::STATUS_CONFIRMED,
            'RECOLECTADO', 'EN BODEGA', 'EN RUTA' => LogisticsShipment::STATUS_IN_TRANSIT,
            'ENTREGADO' => LogisticsShipment::STATUS_DELIVERED,
            'DEVOLUCION', 'DEVOLUCIÓN' => LogisticsShipment::STATUS_RETURNED,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function recipientPayload(LogisticsShipment $shipment): array
    {
        [$state, $city] = $this->mapLocation($shipment->department, $shipment->municipality);

        return array_filter([
            'id_number' => null,
            'name' => (string) ($shipment->recipient_name ?: 'Sin nombre'),
            'phone' => (string) ($shipment->recipient_phone ?? ''),
            'email' => (string) ($shipment->recipient_email ?? ''),
            'address_line_1' => (string) ($shipment->recipient_address ?: 'Sin dirección'),
            'address_line_2' => null,
            'state' => $state,
            'city' => $city,
            'country' => (string) ($shipment->country ?: 'El Salvador'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(LogisticsShipment $shipment, string $recipientId): array
    {
        $description = trim((string) $shipment->description) ?: $shipment->number;
        $clientName = $shipment->client?->name;
        if ($clientName) {
            $description = '['.$clientName.'] '.$description;
        }

        $observations = trim((string) ($shipment->notes ?: 'Envío tercero ROLO CRM'));

        return [
            'order_id' => $shipment->number,
            'sender_id' => $this->client->senderId(),
            'Recipient' => (int) $recipientId,
            'description' => $description,
            'weight' => '1.00',
            'declared_value' => number_format((float) $shipment->collect_amount, 2, '.', ''),
            'use_recipient_address' => 1,
            'payment_type' => ((float) $shipment->collect_amount) > 0.009 ? 'Cash' : 'Prepaid',
            'shipping_status' => 1,
            'shipping_date' => optional($shipment->shipped_at)->toDateString() ?: now()->toDateString(),
            'estimated_shipping_date' => optional($shipment->shipped_at)->toDateString() ?: now()->toDateString(),
            'batches' => '1',
            'is_fragile' => 1,
            'observations' => $observations,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function mapLocation(?string $department, ?string $municipality): array
    {
        $municipality = ElSalvadorGeo::canonicalizeMunicipality($department, $municipality);
        $map = $this->client->departmentCityMap();
        $state = $this->matchKey($department, array_keys($map));
        if ($state === null) {
            throw new RuntimeException(
                'No se pudo mapear el departamento "'.($department ?: '(vacío)').'" a Sistrack.'
            );
        }

        $cities = $map[$state] ?? [];
        $city = $this->matchKey($municipality, $cities);
        if ($city === null) {
            throw new RuntimeException(
                'No se pudo mapear el municipio "'.($municipality ?: '(vacío)').'" en "'.$state.'" (Sistrack).'
            );
        }

        return [$state, $city];
    }

    /**
     * @param  list<string>  $options
     */
    private function matchKey(?string $value, array $options): ?string
    {
        if (! filled($value) || $options === []) {
            return null;
        }

        $needle = $this->normalize($value);
        $aliases = [
            'chalatenango' => 'chaletenango',
            'chaletenango' => 'chalatenango',
            'lilisque' => 'lislique',
            'lislique' => 'lilisque',
        ];
        $needles = array_unique(array_filter([$needle, $aliases[$needle] ?? null]));

        foreach ($options as $option) {
            $normalizedOption = $this->normalize($option);
            if (in_array($normalizedOption, $needles, true)) {
                return $option;
            }
        }

        foreach ($options as $option) {
            $normalizedOption = $this->normalize($option);
            foreach ($needles as $n) {
                if (str_contains($normalizedOption, $n) || str_contains($n, $normalizedOption)) {
                    return $option;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $value);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
