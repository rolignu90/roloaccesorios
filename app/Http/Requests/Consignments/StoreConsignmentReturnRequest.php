<?php

namespace App\Http\Requests\Consignments;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsignmentReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.consignment_item_id' => ['required', 'distinct', 'exists:consignment_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Indica al menos una cantidad a devolver.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($row) => (int) (($row['quantity'] ?? 0)) > 0 && ! empty($row['consignment_item_id']))
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }
}
