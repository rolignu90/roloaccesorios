<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $methods = array_keys(config('sales.payment_methods', ['transfer' => 'Transferencia']));

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', Rule::in($methods)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lot_ids' => ['nullable', 'array'],
            'lot_ids.*' => ['integer', 'exists:inventory_lots,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lotIds = collect($this->input('lot_ids', []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->merge([
            'lot_ids' => $lotIds === [] ? null : $lotIds,
        ]);
    }
}
