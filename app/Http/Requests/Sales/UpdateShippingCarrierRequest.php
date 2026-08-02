<?php

namespace App\Http\Requests\Sales;

use App\Models\ShippingCarrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('shipping_carriers', 'code')->ignore($this->route('shipping_carrier')),
            ],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'commission_type' => ['required', Rule::in(array_keys(ShippingCarrier::COMMISSION_TYPES))],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'code' => filled($this->input('code')) ? strtoupper(trim((string) $this->input('code'))) : null,
        ]);
    }
}
