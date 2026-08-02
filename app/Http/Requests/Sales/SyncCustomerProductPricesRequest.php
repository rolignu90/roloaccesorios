<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SyncCustomerProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.min_quantity' => ['required', 'integer', 'min:1', 'distinct'],
            'tiers.*.unit_price_with_vat' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'tiers.required' => 'Agrega al menos un tramo de cantidad.',
            'tiers.*.min_quantity.distinct' => 'No repitas la misma cantidad mínima.',
            'tiers.*.unit_price_with_vat.required' => 'Cada tramo necesita precio c/IVA.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tiers = collect($this->input('tiers', []))
            ->filter(fn ($row) => filled($row['min_quantity'] ?? null) || filled($row['unit_price_with_vat'] ?? null))
            ->map(fn ($row) => [
                'min_quantity' => (int) ($row['min_quantity'] ?? 0),
                'unit_price_with_vat' => $row['unit_price_with_vat'] ?? null,
            ])
            ->values()
            ->all();

        $this->merge(['tiers' => $tiers]);
    }
}
