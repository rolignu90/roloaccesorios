<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StoreService
{
    public function __construct(private SaleService $sales) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function openSession(Store $store, array $data): CashSession
    {
        if (! $store->is_active) {
            throw new RuntimeException("La tienda {$store->name} está inactiva.");
        }

        $cashier = ! empty($data['cashier_id']) ? Seller::query()->find($data['cashier_id']) : null;

        return DB::transaction(function () use ($store, $data, $cashier) {
            Store::query()->whereKey($store->id)->lockForUpdate()->first();
            if (CashSession::query()->open()->where('store_id', $store->id)->exists()) {
                throw new RuntimeException("{$store->name} ya tiene una caja abierta. Ciérrala antes de abrir otra.");
            }

            return CashSession::query()->create([
                'number' => $this->nextSessionNumber(),
                'store_id' => $store->id,
                'cashier_id' => $cashier?->id,
                'opened_by_user_id' => $data['opened_by_user_id'] ?? null,
                'status' => CashSession::STATUS_OPEN,
                'opened_at' => now(),
                'opened_by' => $data['opened_by'] ?? $cashier?->name,
                'opening_amount' => round(max(0, (float) ($data['opening_amount'] ?? 0)), 2),
                'opening_notes' => $data['opening_notes'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function checkout(Store $store, array $data): Sale
    {
        $session = CashSession::currentFor($store);
        if (! $session) {
            throw new RuntimeException("Abre la caja de {$store->name} antes de cobrar.");
        }

        $method = (string) $data['payment_method'];
        if (! in_array($method, config('sales.store.payment_methods'), true)) {
            throw new InvalidArgumentException('Método de pago no válido para tienda.');
        }

        $customerId = ! empty($data['customer_id'])
            ? (int) $data['customer_id']
            : $this->walkInCustomer()->id;
        $sellerId = ! empty($data['seller_id'])
            ? (int) $data['seller_id']
            : ($session->cashier_id ?? $this->sellerFor($store)->id);

        return DB::transaction(function () use ($data, $store, $session, $method, $customerId, $sellerId) {
            $sale = $this->sales->create([
                'customer_id' => $customerId,
                'seller_id' => $sellerId,
                'sold_at' => now(),
                'payment_method' => $method,
                'has_shipping' => false,
                'shipping_amount' => 0,
                'discount_percent' => (float) ($data['discount_percent'] ?? 0),
                'discount_amount' => (float) ($data['discount_amount'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'] ?? [],
                'combos' => $data['combos'] ?? [],
                'channel' => Sale::CHANNEL_STORE,
            ]);

            $total = round((float) $sale->total, 2);
            $received = null;
            $change = null;

            if ($method === 'cash') {
                $received = filled($data['amount_received'] ?? null)
                    ? round((float) $data['amount_received'], 2)
                    : $total;
                if ($received + 0.009 < $total) {
                    throw new InvalidArgumentException(
                        'El efectivo recibido ('.money($received).') es menor al total ('.money($total).').'
                    );
                }
                $change = round($received - $total, 2);
            }

            $sale->forceFill([
                'cash_session_id' => $session->id,
                'store_id' => $store->id,
                'amount_received' => $received,
                'change_given' => $change,
                'status' => Sale::STATUS_DELIVERED,
                'status_changed_at' => now(),
            ])->save();

            return $sale->fresh(['customer', 'seller', 'items.product', 'items.combo', 'cashSession', 'store']);
        });
    }

    public function changeCashier(CashSession $session, ?Seller $cashier): CashSession
    {
        if (! $session->isOpen()) {
            throw new RuntimeException('La caja ya está cerrada.');
        }

        $session->update(['cashier_id' => $cashier?->id]);

        return $session->fresh('cashier');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMovement(CashSession $session, array $data): CashMovement
    {
        if (! $session->isOpen()) {
            throw new RuntimeException('La caja ya está cerrada.');
        }

        return $session->movements()->create([
            'type' => $data['type'],
            'user_id' => $data['user_id'] ?? null,
            'amount' => round(max(0, (float) $data['amount']), 2),
            'reason' => trim((string) $data['reason']),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array{
     *   sales_count: int,
     *   sales_total: float,
     *   by_method: array<string, float>,
     *   cash_in_total: float,
     *   cash_out_total: float,
     *   expected_cash: float
     * }
     */
    public function summary(CashSession $session): array
    {
        if (! $session->isOpen()) {
            return [
                'sales_count' => (int) $session->sales_count,
                'sales_total' => (float) $session->sales_total,
                'by_method' => [
                    'cash' => (float) $session->cash_sales_total,
                    'card' => (float) $session->card_sales_total,
                    'transfer' => (float) $session->transfer_sales_total,
                    'other' => (float) $session->other_sales_total,
                ],
                'cash_in_total' => (float) $session->cash_in_total,
                'cash_out_total' => (float) $session->cash_out_total,
                'expected_cash' => (float) $session->expected_cash,
            ];
        }

        $rows = Sale::query()
            ->where('cash_session_id', $session->id)
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->selectRaw('payment_method, COUNT(*) as cnt, SUM(total) as amount')
            ->groupBy('payment_method')
            ->get();

        $byMethod = ['cash' => 0.0, 'card' => 0.0, 'transfer' => 0.0, 'other' => 0.0];
        foreach ($rows as $row) {
            $key = array_key_exists($row->payment_method, $byMethod) ? $row->payment_method : 'other';
            $byMethod[$key] = round($byMethod[$key] + (float) $row->amount, 2);
        }

        $cashIn = round((float) $session->movements()->where('type', CashMovement::TYPE_IN)->sum('amount'), 2);
        $cashOut = round((float) $session->movements()->where('type', CashMovement::TYPE_OUT)->sum('amount'), 2);

        return [
            'sales_count' => (int) $rows->sum('cnt'),
            'sales_total' => round((float) $rows->sum('amount'), 2),
            'by_method' => $byMethod,
            'cash_in_total' => $cashIn,
            'cash_out_total' => $cashOut,
            'expected_cash' => round((float) $session->opening_amount + $byMethod['cash'] + $cashIn - $cashOut, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function closeSession(CashSession $session, array $data): CashSession
    {
        return DB::transaction(function () use ($session, $data) {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $session->isOpen()) {
                throw new RuntimeException('Esta caja ya está cerrada.');
            }

            $summary = $this->summary($session);
            $counted = round(max(0, (float) $data['counted_cash']), 2);

            $session->update([
                'status' => CashSession::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by' => $data['closed_by'] ?? null,
                'closed_by_user_id' => $data['closed_by_user_id'] ?? null,
                'sales_count' => $summary['sales_count'],
                'sales_total' => $summary['sales_total'],
                'cash_sales_total' => $summary['by_method']['cash'],
                'card_sales_total' => $summary['by_method']['card'],
                'transfer_sales_total' => $summary['by_method']['transfer'],
                'other_sales_total' => $summary['by_method']['other'],
                'cash_in_total' => $summary['cash_in_total'],
                'cash_out_total' => $summary['cash_out_total'],
                'expected_cash' => $summary['expected_cash'],
                'counted_cash' => $counted,
                'difference' => round($counted - $summary['expected_cash'], 2),
                'closing_notes' => $data['closing_notes'] ?? null,
            ]);

            return $session->fresh();
        });
    }

    public function walkInCustomer(): Customer
    {
        $name = (string) config('sales.store.walk_in_customer_name', 'Consumidor final');

        $existing = Customer::query()->where('name', $name)->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        return Customer::query()->create([
            'code' => Customer::nextCode(),
            'name' => $name,
            'document_type' => 'N/A',
            'country' => 'El Salvador',
            'notes' => 'Cliente genérico para ventas en tienda física.',
            'is_active' => true,
        ]);
    }

    /**
     * The store's default seller; creates one with the store's ticket prefix if missing.
     */
    public function sellerFor(Store $store): Seller
    {
        if ($store->seller) {
            return $store->seller;
        }

        $seller = Seller::query()->whereIn('sale_prefix', ['T-', 'T'])->orderBy('id')->first()
            ?? $this->createStoreSeller($store->name, 'T-');

        $store->update(['seller_id' => $seller->id]);
        $store->setRelation('seller', $seller);

        return $seller;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveStore(Store $store, array $data): Store
    {
        return DB::transaction(function () use ($store, $data) {
            $prefix = Seller::normalizeSalePrefix($data['ticket_prefix'] ?? null);
            unset($data['ticket_prefix']);

            if (empty($data['seller_id']) && $prefix !== null) {
                $taken = Seller::query()->where('sale_prefix', $prefix)->first();
                if ($taken) {
                    throw new InvalidArgumentException(
                        "El prefijo {$prefix} ya lo usa el vendedor {$taken->name}. Elígelo como vendedor o usa otro prefijo."
                    );
                }
                $data['seller_id'] = $this->createStoreSeller((string) $data['name'], $prefix)->id;
            }

            $store->fill($data)->save();

            return $store->fresh('seller');
        });
    }

    private function createStoreSeller(string $storeName, string $prefix): Seller
    {
        return Seller::query()->create([
            'code' => Seller::nextCode(),
            'sale_prefix' => $prefix,
            'name' => $storeName,
            'type' => Seller::TYPE_INTERNAL,
            'salary_amount' => 0,
            'is_active' => true,
            'notes' => 'Vendedor por defecto de la tienda física.',
        ]);
    }

    private function nextSessionNumber(): string
    {
        $prefix = 'CAJA-'.now()->format('Ymd').'-';
        $max = CashSession::query()
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(number, '.(strlen($prefix) + 1).') AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return $prefix.str_pad((string) (((int) $max) + 1), 2, '0', STR_PAD_LEFT);
    }
}
