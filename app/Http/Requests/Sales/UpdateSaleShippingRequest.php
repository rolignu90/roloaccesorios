<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'shipping_amount' => $this->input('shipping_amount', 0),
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }
}
