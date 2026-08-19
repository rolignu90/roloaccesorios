<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComboRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $comboId = $this->route('combo')?->id ?? $this->route('combo');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('combos', 'code')->ignore($comboId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sale_price_with_vat' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'free_shipping' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos 2 productos al combo.',
            'items.min' => 'Un combo debe incluir al menos 2 productos.',
            'items.*.product_id.distinct' => 'No repitas el mismo producto en el combo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => filled($row['product_id'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code', ''))),
            'is_active' => $this->boolean('is_active'),
            'free_shipping' => $this->boolean('free_shipping'),
            'items' => $items,
        ]);
    }

    public function comboAttributes(): array
    {
        return [
            'code' => $this->validated('code'),
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
            'sale_price_without_vat' => price_without_vat($this->validated('sale_price_with_vat')),
            'is_active' => $this->boolean('is_active'),
            'free_shipping' => $this->boolean('free_shipping'),
        ];
    }
}
