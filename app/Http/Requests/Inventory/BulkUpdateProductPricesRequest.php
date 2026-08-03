<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'products.*.sale_price_with_vat' => ['required', 'numeric', 'min:0'],
            'products.*.wholesale_price_with_vat' => ['nullable', 'numeric', 'min:0'],
            'products.*.promo_active' => ['sometimes', 'boolean'],
            'products.*.promo_type' => ['nullable', Rule::in([Product::PROMO_AMOUNT, Product::PROMO_PERCENT])],
            'products.*.promo_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'products.required' => 'No hay productos para actualizar.',
            'products.*.sale_price_with_vat.required' => 'El precio de venta es obligatorio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $products = collect($this->input('products', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['id']))
            ->map(function (array $row) {
                $promoActive = filter_var($row['promo_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return [
                    'id' => (int) $row['id'],
                    'sale_price_with_vat' => $row['sale_price_with_vat'] ?? 0,
                    'wholesale_price_with_vat' => ($row['wholesale_price_with_vat'] ?? '') === ''
                        ? null
                        : $row['wholesale_price_with_vat'],
                    'promo_active' => $promoActive,
                    'promo_type' => $promoActive ? ($row['promo_type'] ?? Product::PROMO_AMOUNT) : null,
                    'promo_value' => $promoActive
                        ? ($row['promo_value'] ?? null)
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->merge(['products' => $products]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('products', []) as $index => $row) {
                if (! ($row['promo_active'] ?? false)) {
                    continue;
                }

                if ($row['promo_value'] === null || $row['promo_value'] === '') {
                    $validator->errors()->add(
                        "products.$index.promo_value",
                        'Indica el valor de la promoción.'
                    );
                }

                if (($row['promo_type'] ?? null) === Product::PROMO_PERCENT
                    && (float) ($row['promo_value'] ?? 0) > 100) {
                    $validator->errors()->add(
                        "products.$index.promo_value",
                        'El descuento % no puede superar 100.'
                    );
                }
            }
        });
    }
}
