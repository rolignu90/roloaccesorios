<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\ShippingCarrier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EcommerceOrderService
{
    public function __construct(private SaleService $sales) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sale: Sale, created: bool}
     */
    public function createOrFind(array $payload): array
    {
        $externalId = (string) $payload['external_order_id'];

        $existing = Sale::query()
            ->where('external_order_id', $externalId)
            ->first();

        if ($existing) {
            return ['sale' => $existing->fresh([
                'customer',
                'seller',
                'shippingCarrier',
                'items.product',
                'items.combo',
            ]), 'created' => false];
        }

        return DB::transaction(function () use ($payload, $externalId) {
            // Re-check under lock-friendly unique constraint path.
            $existing = Sale::query()
                ->where('external_order_id', $externalId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['sale' => $existing->fresh([
                    'customer',
                    'seller',
                    'shippingCarrier',
                    'items.product',
                    'items.combo',
                ]), 'created' => false];
            }

            $seller = $this->resolveSeller();
            $customer = $this->resolveCustomer($payload['customer'] ?? []);
            [$items, $combos] = $this->resolveLineItems($payload['items'] ?? []);

            if ($items === [] && $combos === []) {
                throw ValidationException::withMessages([
                    'items' => 'No se pudieron resolver productos/combos válidos.',
                ]);
            }

            if (! config('ecommerce.allow_on_demand', true)) {
                $this->assertStockAvailable($items, $combos);
            }

            $hasShipping = array_key_exists('has_shipping', $payload)
                ? (bool) $payload['has_shipping']
                : true;

            if ($hasShipping) {
                $carrierId = (int) ($payload['shipping_carrier_id'] ?? 0);
                $carrier = ShippingCarrier::query()
                    ->where('is_active', true)
                    ->find($carrierId);
                if (! $carrier) {
                    throw ValidationException::withMessages([
                        'shipping_carrier_id' => 'Empresa de envío inválida o inactiva.',
                    ]);
                }
            }

            try {
                $sale = $this->sales->create([
                    'customer_id' => $customer->id,
                    'seller_id' => $seller->id,
                    'sold_at' => now(),
                    'payment_method' => $payload['payment_method'],
                    'has_shipping' => $hasShipping,
                    'shipping_carrier_id' => $hasShipping ? (int) $payload['shipping_carrier_id'] : null,
                    'shipping_amount' => $payload['shipping_amount']
                        ?? config('ecommerce.default_shipping_amount'),
                    'notes' => $payload['notes'] ?? null,
                    'channel' => Sale::CHANNEL_ECOMMERCE,
                    'external_order_id' => $externalId,
                    'items' => $items,
                    'combos' => $combos,
                ]);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    $existing = Sale::query()->where('external_order_id', $externalId)->first();
                    if ($existing) {
                        return ['sale' => $existing->fresh([
                            'customer',
                            'seller',
                            'shippingCarrier',
                            'items.product',
                            'items.combo',
                        ]), 'created' => false];
                    }
                }

                throw $e;
            }

            return ['sale' => $sale, 'created' => true];
        });
    }

    public function findByExternalOrderId(string $externalOrderId): ?Sale
    {
        return Sale::query()
            ->where('external_order_id', $externalOrderId)
            ->with(['customer', 'seller', 'shippingCarrier', 'items.product', 'items.combo'])
            ->first();
    }

    public function findByNumber(string $number): ?Sale
    {
        return Sale::query()
            ->where('number', $number)
            ->with(['customer', 'seller', 'shippingCarrier', 'items.product', 'items.combo'])
            ->first();
    }

    private function resolveSeller(): Seller
    {
        $sellerId = config('ecommerce.seller_id');
        if ($sellerId) {
            $seller = Seller::query()
                ->where('is_active', true)
                ->find((int) $sellerId);
            if ($seller) {
                return $seller;
            }
        }

        $code = (string) config('ecommerce.seller_code', 'WEB');
        $seller = Seller::query()
            ->where('is_active', true)
            ->where('code', $code)
            ->first();

        if (! $seller) {
            throw new RuntimeException(
                "Vendedor e-commerce no configurado. Define ECOMMERCE_SELLER_ID o crea un vendedor con código {$code}."
            );
        }

        return $seller;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): Customer
    {
        $phoneRaw = (string) ($data['phone'] ?? '');
        $normalized = Customer::normalizePhone($phoneRaw);

        if ($normalized) {
            $match = Customer::query()
                ->where('is_active', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderByDesc('id')
                ->get(['id', 'phone'])
                ->first(fn (Customer $c) => $c->normalizedPhone() === $normalized);

            if ($match) {
                $customer = Customer::query()->findOrFail($match->id);
                $updates = array_filter([
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                    'department' => $data['department'] ?? null,
                    'municipality' => $data['municipality'] ?? null,
                    'country' => $data['country'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'phone' => $phoneRaw,
                ], fn ($v) => $v !== null && $v !== '');

                if ($updates !== []) {
                    $customer->fill($updates)->save();
                }

                return $customer->fresh();
            }
        }

        return Customer::createWithNextCode([
            'name' => (string) ($data['name'] ?? 'Cliente web'),
            'document_type' => 'N/A',
            'document_number' => null,
            'email' => $data['email'] ?? null,
            'phone' => $phoneRaw ?: null,
            'address' => $data['address'] ?? null,
            'department' => $data['department'] ?? null,
            'municipality' => $data['municipality'] ?? null,
            'country' => $data['country'] ?? 'El Salvador',
            'postal_code' => $data['postal_code'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: list<array{combo_id:int,quantity:int}>}
     */
    private function resolveLineItems(array $rows): array
    {
        $items = [];
        $combos = [];

        foreach ($rows as $index => $row) {
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            if (filled($row['combo_code'] ?? null)) {
                $combo = Combo::query()
                    ->where('is_active', true)
                    ->where('code', $row['combo_code'])
                    ->first();

                if (! $combo) {
                    throw ValidationException::withMessages([
                        "items.$index.combo_code" => "Combo no encontrado: {$row['combo_code']}",
                    ]);
                }

                $combos[] = [
                    'combo_id' => $combo->id,
                    'quantity' => $qty,
                ];

                continue;
            }

            $product = Product::query()
                ->where('is_active', true)
                ->where('code', $row['product_code'] ?? '')
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.$index.product_code" => 'Producto no encontrado: '.($row['product_code'] ?? ''),
                ]);
            }

            $unitWithVat = $product->effectiveSalePriceWithVat();

            $items[] = [
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price_with_vat' => $unitWithVat,
                'unit_price_without_vat' => price_without_vat($unitWithVat),
                'discount_percent' => 0,
                'discount_amount' => 0,
            ];
        }

        return [$items, $combos];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{combo_id:int,quantity:int}>  $combos
     */
    private function assertStockAvailable(array $items, array $combos): void
    {
        $needed = [];

        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $needed[$pid] = ($needed[$pid] ?? 0) + (int) $item['quantity'];
        }

        foreach ($combos as $row) {
            $combo = Combo::query()
                ->with('items.product')
                ->findOrFail($row['combo_id']);
            foreach ($combo->expandToSaleLines((int) $row['quantity']) as $expanded) {
                $pid = $expanded['product']->id;
                $needed[$pid] = ($needed[$pid] ?? 0) + (int) $expanded['quantity'];
            }
        }

        foreach ($needed as $productId => $qty) {
            $product = Product::query()->findOrFail($productId);
            if ($product->on_demand) {
                continue;
            }
            $stock = $product->stockOnHand();
            if ($stock < $qty) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para {$product->code}: hay {$stock}, se piden {$qty}.",
                ]);
            }
        }
    }
}
