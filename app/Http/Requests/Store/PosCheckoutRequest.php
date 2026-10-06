<?php

namespace App\Http\Requests\Store;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'seller_id' => ['nullable', 'integer', Rule::exists('sellers', 'id')->where('is_active', true)],
            'payment_method' => ['required', Rule::in(config('sales.store.payment_methods'))],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_with_vat' => ['required', 'numeric', 'min:0'],
            'combos' => ['nullable', 'array'],
            'combos.*.combo_id' => ['required', 'integer', 'exists:combos,id'],
            'combos.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('items')) && empty($this->input('combos'))) {
                $validator->errors()->add('items', 'Agrega al menos un producto al carrito.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        $canOverridePrice = (bool) $user?->hasPermission('pos.price_override');
        $listPrices = $canOverridePrice ? collect() : Product::query()
            ->whereIn('id', collect($this->input('items', []))->pluck('product_id')->filter()->map(fn ($id) => (int) $id))
            ->get()
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->effectiveSalePriceWithVat()]);

        $items = collect($this->input('items', []))
            ->map(function ($row) use ($canOverridePrice, $listPrices) {
                if (is_array($row) && ! $canOverridePrice && $listPrices->has((int) ($row['product_id'] ?? 0))) {
                    $row['unit_price_with_vat'] = $listPrices->get((int) $row['product_id']);
                }

                return $row;
            })
            ->filter(fn ($row) => is_array($row) && filled($row['product_id'] ?? null) && (int) ($row['quantity'] ?? 0) > 0)
            ->map(fn (array $row) => [
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
                'unit_price_with_vat' => round((float) ($row['unit_price_with_vat'] ?? 0), 2),
                'unit_price_without_vat' => price_without_vat((float) ($row['unit_price_with_vat'] ?? 0)),
            ])
            ->values()
            ->all();

        $combos = collect($this->input('combos', []))
            ->filter(fn ($row) => is_array($row) && filled($row['combo_id'] ?? null) && (int) ($row['quantity'] ?? 0) > 0)
            ->map(fn (array $row) => [
                'combo_id' => (int) $row['combo_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
            'combos' => $combos,
            'discount_percent' => $user?->hasPermission('pos.discount') ? ($this->input('discount_percent') ?: 0) : 0,
            'discount_amount' => $user?->hasPermission('pos.discount') ? ($this->input('discount_amount') ?: 0) : 0,
        ]);
    }

    /**
     * Keeps unit_price_without_vat, which isn't a validated key.
     */
    public function checkoutData(): array
    {
        $data = $this->validated();
        $data['items'] = $this->input('items', []);

        return $data;
    }
}
