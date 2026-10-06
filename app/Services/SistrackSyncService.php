<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\ElSalvadorGeo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SistrackSyncService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        private SistrackClient $client,
        private SaleLabelExportService $labelExport,
        private SaleService $sales,
    ) {}

    public function canSend(Sale $sale): bool
    {
        return $sale->canSendToSistrack();
    }

    /**
     * @param  Collection<int, Sale>|iterable<Sale>  $sales
     * @return array{sent: list<Sale>, failed: list<array{sale: Sale, error: string}>, skipped: list<array{sale: Sale, reason: string}>}
     */
    public function sendMany(iterable $sales): array
    {
        $sent = [];
        $failed = [];
        $skipped = [];

        foreach ($sales as $sale) {
            if ($sale->sistrack_status === self::STATUS_SENT) {
                $skipped[] = ['sale' => $sale, 'reason' => 'Ya enviado a Sistrack'];

                continue;
            }
            if (! $sale->canSendToSistrack()) {
                $skipped[] = ['sale' => $sale, 'reason' => 'La empresa de envío no tiene Sistrack o la venta no aplica'];

                continue;
            }

            try {
                $sent[] = $this->sendOne($sale);
            } catch (Throwable $e) {
                $failed[] = ['sale' => $sale->fresh(), 'error' => $e->getMessage()];
            }
        }

        return compact('sent', 'failed', 'skipped');
    }

    public function resendOne(Sale $sale): Sale
    {
        if (! $sale->canResendToSistrack()) {
            throw new RuntimeException('Esta venta no se puede reenviar a Sistrack (revisa que ya se haya enviado y que la empresa tenga Sistrack).');
        }

        $sale->forceFill([
            'sistrack_status' => self::STATUS_PENDING,
            'sistrack_external_id' => null,
            'sistrack_order_id' => null,
            'sistrack_shipping_status' => null,
            'sistrack_status_synced_at' => null,
            'sistrack_last_error' => null,
        ])->save();

        return $this->sendOne($sale->fresh(), force: true);
    }

    public function sendOne(Sale $sale, bool $force = false): Sale
    {
        if (! $force && $sale->sistrack_status === self::STATUS_SENT && filled($sale->sistrack_external_id)) {
            return $sale;
        }

        if ($force) {
            if (! $sale->isEligibleForSistrackPush()) {
                throw new RuntimeException('Esta venta no se puede reenviar a Sistrack (revisa empresa de envío y credenciales).');
            }
        } elseif (! $sale->canSendToSistrack()) {
            throw new RuntimeException('Esta venta no se puede enviar a Sistrack (revisa empresa de envío y credenciales).');
        }

        $sale->loadMissing(['customer', 'seller', 'items.product', 'shippingCarrier']);
        $customer = $sale->customer;
        if (! $customer) {
            throw new RuntimeException('La venta no tiene cliente.');
        }

        $carrier = $sale->shippingCarrier;
        if (! $carrier) {
            throw new RuntimeException('La venta no tiene empresa de envío.');
        }

        $sale->forceFill([
            'sistrack_last_attempt_at' => now(),
            'sistrack_last_error' => null,
        ])->save();

        try {
            $this->client->useCarrier($carrier)->login();

            // Evitar duplicados: si ya existe en Sistrack por Id Comercio, marcar enviada.
            // En reenvío se crea etiqueta nueva (la anterior se borró en Sistrack).
            if (! $force) {
                $existing = $this->client->findBestOrderByCommerceId($sale->number);
                if ($existing) {
                    $sale->forceFill([
                        'sistrack_status' => self::STATUS_SENT,
                        'sistrack_external_id' => (string) $existing['id'],
                        'sistrack_order_id' => $sale->number,
                        'sistrack_shipping_status' => $existing['shipping_status'] !== null
                            ? (string) $existing['shipping_status']
                            : $sale->sistrack_shipping_status,
                        'sistrack_last_error' => null,
                        'sistrack_last_attempt_at' => now(),
                    ])->save();

                    return $sale->fresh();
                }
            }

            $recipientPayload = $this->recipientPayload($sale);
            $recipientId = $sale->sistrack_recipient_id
                ?: $this->client->searchRecipientId($customer->phone ?: $customer->name, $customer->name, $customer->phone);

            if ($recipientId) {
                // Reutilizar ID pero refrescar dirección/depto (evita datos viejos o mal mapeados).
                $this->client->updateRecipient((string) $recipientId, $recipientPayload);
            } else {
                $recipientId = $this->client->createRecipient($recipientPayload)['id'];
            }

            $order = $this->client->createOrder($this->orderPayload($sale, (string) $recipientId));

            $createdId = (string) $order['id'];
            if (! $force) {
                $best = $this->client->findBestOrderByCommerceId($sale->number);
                if ($best) {
                    $createdId = (string) $best['id'];
                }
            }

            $sale->forceFill([
                'sistrack_status' => self::STATUS_SENT,
                'sistrack_external_id' => $createdId,
                'sistrack_order_id' => $sale->number,
                'sistrack_recipient_id' => (string) $recipientId,
                'sistrack_last_error' => null,
                'sistrack_last_attempt_at' => now(),
            ])->save();

            return $sale->fresh();
        } catch (Throwable $e) {
            $sale->forceFill([
                'sistrack_status' => self::STATUS_FAILED,
                'sistrack_last_error' => Str::limit($e->getMessage(), 2000),
                'sistrack_last_attempt_at' => now(),
            ])->save();

            throw $e;
        }
    }

    /**
     * Pushes current customer + total/description to the existing Sistrack order (no new guide).
     *
     * @return array{sale: Sale, warnings: list<string>}
     */
    public function updateInSistrack(Sale $sale): array
    {
        if (! $sale->hasSistrackLabel()) {
            throw new RuntimeException('Esta venta no está en Sistrack.');
        }

        $sale->loadMissing(['customer', 'seller', 'items.product', 'shippingCarrier']);
        if (! $sale->customer) {
            throw new RuntimeException('La venta no tiene cliente.');
        }
        $carrier = $sale->shippingCarrier;
        if (! $carrier?->supportsSistrack()) {
            throw new RuntimeException('La empresa de envío no tiene Sistrack.');
        }

        $this->client->useCarrier($carrier)->login();
        $externalId = (string) $sale->sistrack_external_id;
        $recipientPayload = $this->recipientPayload($sale);

        $recipientId = $sale->sistrack_recipient_id;
        if (! filled($recipientId)) {
            $label = (string) ($this->client->getOrder($externalId)['fields']['Recipient'] ?? '');
            $recipientId = preg_match('/^\s*(\d+)/', $label, $m) ? $m[1] : null;
        }
        if (! filled($recipientId)) {
            throw new RuntimeException('No se encontró el destinatario de la orden en Sistrack.');
        }

        $this->client->updateRecipient((string) $recipientId, $recipientPayload);

        $order = $this->orderPayload($sale, (string) $recipientId);
        $result = $this->client->updateOrder($externalId, [
            'description' => $order['description'],
            'declared_value' => $order['declared_value'],
            'use_recipient_address' => 1,
        ]);

        $warnings = [];
        if (($result['fields']['payment_type'] ?? null) && $result['fields']['payment_type'] !== $order['payment_type']) {
            $warnings[] = 'Sistrack no permite cambiar el tipo de pago ('.$result['fields']['payment_type'].' → '.$order['payment_type'].'); usa «Reenviar» si cambió la forma de pago.';
        }

        $orderAddress = $result['resource']['address_line_1_order'] ?? null;
        $orderCity = $result['resource']['city_order'] ?? null;
        $sameText = fn ($a, $b) => Str::lower(trim((string) $a)) === Str::lower(trim((string) $b));
        if ($orderAddress !== null && (! $sameText($orderAddress, $recipientPayload['address_line_1'] ?? '') || ! $sameText($orderCity, $recipientPayload['city'] ?? ''))) {
            $warnings[] = 'El destinatario se actualizó, pero la orden conserva la dirección anterior ('.$orderAddress.', '.$orderCity.'). Corrígela en Sistrack o usa «Reenviar».';
        }

        $sale->forceFill([
            'sistrack_recipient_id' => (string) $recipientId,
            'sistrack_last_attempt_at' => now(),
            'sistrack_last_error' => null,
        ])->save();

        return ['sale' => $sale->fresh(), 'warnings' => $warnings];
    }

    /**
     * Sync shipping status from Sistrack into local sale.status.
     *
     * @param  Collection<int, Sale>|iterable<Sale>  $sales
     * @return array{
     *   updated: list<Sale>,
     *   unchanged: list<Sale>,
     *   failed: list<array{sale: Sale, error: string}>,
     *   skipped: list<array{sale: Sale, reason: string}>
     * }
     */
    public function syncStatuses(iterable $sales): array
    {
        $updated = [];
        $unchanged = [];
        $failed = [];
        $skipped = [];

        /** @var array<int, list<Sale>> $byCarrier */
        $byCarrier = [];
        foreach ($sales as $sale) {
            if (! $sale->canSyncSistrackStatus()) {
                $skipped[] = ['sale' => $sale, 'reason' => 'No enviada a Sistrack o anulada'];

                continue;
            }
            $carrierId = (int) ($sale->shipping_carrier_id ?? 0);
            $byCarrier[$carrierId][] = $sale;
        }

        foreach ($byCarrier as $carrierSales) {
            $first = $carrierSales[0];
            $first->loadMissing('shippingCarrier');
            $carrier = $first->shippingCarrier;
            if (! $carrier?->supportsSistrack()) {
                foreach ($carrierSales as $sale) {
                    $skipped[] = ['sale' => $sale, 'reason' => 'Empresa sin Sistrack'];
                }

                continue;
            }

            try {
                $this->client->useCarrier($carrier)->login();
            } catch (Throwable $e) {
                foreach ($carrierSales as $sale) {
                    $failed[] = ['sale' => $sale, 'error' => $e->getMessage()];
                }

                continue;
            }

            foreach ($carrierSales as $sale) {
                try {
                    $result = $this->syncOneStatus($sale, ensureLogin: false);
                    if ($result['changed']) {
                        $updated[] = $result['sale'];
                    } else {
                        $unchanged[] = $result['sale'];
                    }
                } catch (Throwable $e) {
                    $failed[] = ['sale' => $sale->fresh() ?? $sale, 'error' => $e->getMessage()];
                }
            }
        }

        return compact('updated', 'unchanged', 'failed', 'skipped');
    }

    /**
     * @return array{sale: Sale, changed: bool}
     */
    public function syncOneStatus(Sale $sale, bool $ensureLogin = true): array
    {
        if (! $sale->canSyncSistrackStatus()) {
            throw new RuntimeException('Esta venta no se puede sincronizar con Sistrack.');
        }

        $sale->loadMissing('shippingCarrier');
        $carrier = $sale->shippingCarrier;
        if (! $carrier?->supportsSistrack()) {
            throw new RuntimeException('La empresa de envío no tiene Sistrack.');
        }

        if ($ensureLogin) {
            $this->client->useCarrier($carrier)->login();
        }

        $resolved = $this->resolveSistrackOrderForSale($sale);
        $order = $resolved['order'];
        $externalId = (string) $resolved['id'];

        $rawStatus = $order['fields']['shipping_status']
            ?? $order['fields']['shipping_status_label']
            ?? null;

        $mapped = $this->mapShippingStatusToSaleStatus($rawStatus);
        $rawStored = $rawStatus === null ? null : (string) $rawStatus;

        $sale->forceFill([
            'sistrack_external_id' => $externalId,
            'sistrack_order_id' => $sale->number,
            'sistrack_shipping_status' => $rawStored,
            'sistrack_status_synced_at' => now(),
        ]);

        $changed = false;
        if ($mapped && $sale->canProgressToStatus($mapped) && $sale->status !== $mapped) {
            if ($mapped === Sale::STATUS_RETURNED) {
                $sale->save();
                $this->sales->markReturned($sale->fresh());
                $changed = true;

                return ['sale' => $sale->fresh(), 'changed' => $changed];
            }

            if ($mapped === Sale::STATUS_DELIVERED) {
                $sale->save();
                $this->sales->markDelivered($sale->fresh());
                $changed = true;

                return ['sale' => $sale->fresh(), 'changed' => $changed];
            }

            $sale->status = $mapped;
            $sale->status_changed_at = now();
            $changed = true;
        }

        $sale->save();

        return ['sale' => $sale->fresh(), 'changed' => $changed];
    }

    /**
     * Resuelve la orden Sistrack correcta para la venta.
     * Si hay duplicados del mismo Id Comercio, elige el de estado más avanzado.
     *
     * @return array{id: string, order: array{id: int|string, fields: array<string, mixed>}}
     */
    private function resolveSistrackOrderForSale(Sale $sale): array
    {
        $candidates = [];

        if (filled($sale->sistrack_external_id)) {
            try {
                $order = $this->client->getOrder($sale->sistrack_external_id);
                $orderId = (string) ($order['fields']['order_id'] ?? '');
                // Solo aceptar si el Id Comercio coincide (evita IDs cruzados).
                if ($orderId === '' || $orderId === (string) $sale->number) {
                    $candidates[] = [
                        'id' => (string) $order['id'],
                        'shipping_status' => $order['fields']['shipping_status']
                            ?? $order['fields']['shipping_status_label']
                            ?? null,
                        'order' => $order,
                    ];
                }
            } catch (Throwable) {
                // ID inválido / borrado: se resolverá por Id Comercio.
            }
        }

        foreach ($this->client->findOrdersByCommerceId((string) $sale->number) as $match) {
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
                'No se encontró orden Sistrack para '.$sale->number
                .(filled($sale->sistrack_external_id) ? ' (ID '.$sale->sistrack_external_id.')' : '').'.'
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

    /**
     * Map Sistrack shipping_status (label or numeric code) to local sale status.
     *
     * Sistrack codes: 1 CREADO, 2 RECOLECTADO, 3 EN BODEGA, 4 EN RUTA, 8 ENTREGADO, 9 DEVOLUCION
     */
    public function mapShippingStatusToSaleStatus(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return match ((int) $raw) {
                1 => Sale::STATUS_CONFIRMED,
                2, 3, 4 => Sale::STATUS_IN_TRANSIT,
                8 => Sale::STATUS_DELIVERED,
                9 => Sale::STATUS_RETURNED,
                default => null,
            };
        }

        $normalized = Str::upper(trim((string) $raw));
        $normalized = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $normalized);

        return match ($normalized) {
            'CREADO' => Sale::STATUS_CONFIRMED,
            'RECOLECTADO', 'EN BODEGA', 'EN RUTA' => Sale::STATUS_IN_TRANSIT,
            'ENTREGADO' => Sale::STATUS_DELIVERED,
            'DEVOLUCION', 'DEVOLUCIÓN' => Sale::STATUS_RETURNED,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function recipientPayload(Sale $sale): array
    {
        $customer = $sale->customer;
        [$state, $city] = $this->mapLocation($customer?->department, $customer?->municipality);

        return array_filter([
            'id_number' => null,
            'name' => (string) ($customer?->name ?? 'Sin nombre'),
            'phone' => (string) ($customer?->phone ?? ''),
            'email' => (string) ($customer?->email ?? ''),
            'address_line_1' => (string) ($customer?->address ?? 'Sin dirección'),
            'address_line_2' => null,
            'state' => $state,
            'city' => $city,
            'country' => (string) ($customer?->country ?: 'El Salvador'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Sale $sale, string $recipientId): array
    {
        $row = $this->labelExport->mapSale($sale);
        $description = (string) ($row['DESCRIPCION'] ?? $sale->number);
        $weight = (float) ($row['PESO'] ?? 0);
        if ($weight <= 0) {
            $weight = 1;
        }

        $paymentType = match ($sale->payment_method) {
            'card' => 'TDC',
            'transfer' => 'Wire',
            'credit' => 'Prepaid',
            default => 'Cash',
        };

        $observations = trim((string) ($sale->notes ?: 'Enviado desde ROLO CRM'));
        if ($sale->isPaidBeforeShipping()) {
            $observations = trim($observations."\nPagado por transferencia. Cobrar $0.00.");
        }

        return [
            'order_id' => $sale->number,
            'sender_id' => $this->client->senderId(),
            'Recipient' => (int) $recipientId,
            'description' => $description,
            'weight' => number_format($weight, 2, '.', ''),
            'declared_value' => number_format($sale->sistrackCollectAmount(), 2, '.', ''),
            'use_recipient_address' => 1,
            'payment_type' => $paymentType,
            'shipping_status' => 1, // CREADO
            'shipping_date' => optional($sale->sold_at)->toDateString() ?: now()->toDateString(),
            'estimated_shipping_date' => optional($sale->sold_at)->toDateString() ?: now()->toDateString(),
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
                'No se pudo mapear el departamento "'.($department ?: '(vacío)').'" a Sistrack. Revisa el nombre en el cliente.'
            );
        }

        $cities = $map[$state] ?? [];
        $city = $this->matchKey($municipality, $cities);
        if ($city === null) {
            throw new RuntimeException(
                'No se pudo mapear el municipio "'.($municipality ?: '(vacío)').'" en "'.$state.'" (Sistrack). Revisa el cliente.'
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
            // Sistrack usa typo histórico "Chaletenango"
            'chalatenango' => 'chaletenango',
            'chaletenango' => 'chalatenango',
            // Catálogo local tenía typo "Lilisque"; oficial/Sistrack: "Lislique"
            'lilisque' => 'lislique',
            'lislique' => 'lilisque',
        ];
        $needles = array_unique(array_filter([
            $needle,
            $aliases[$needle] ?? null,
        ]));

        foreach ($options as $option) {
            $opt = $this->normalize($option);
            if (in_array($opt, $needles, true)) {
                return $option;
            }
        }

        // Contiene / contenido.
        foreach ($options as $option) {
            $opt = $this->normalize($option);
            foreach ($needles as $n) {
                if (str_contains($opt, $n) || str_contains($n, $opt)) {
                    return $option;
                }
            }
        }

        // Typo cercano (p. ej. Chalatenango vs Chaletenango).
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($options as $option) {
            $opt = $this->normalize($option);
            foreach ($needles as $n) {
                $maxLen = max(strlen($n), strlen($opt));
                if ($maxLen === 0) {
                    continue;
                }
                $distance = levenshtein($n, $opt);
                $threshold = $maxLen <= 8 ? 1 : 2;
                if ($distance <= $threshold && $distance < $bestDistance) {
                    $best = $option;
                    $bestDistance = $distance;
                }
            }
        }

        return $best;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($trans) && $trans !== '') {
            $value = $trans;
        }

        return preg_replace('/[^a-z0-9]+/', '', $value) ?: $value;
    }
}
