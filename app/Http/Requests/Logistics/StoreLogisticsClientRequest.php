<?php

namespace App\Http\Requests\Logistics;

use App\Models\LogisticsClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogisticsClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'commission_type' => ['required', Rule::in(array_keys(LogisticsClient::COMMISSION_TYPES))],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'default_shipping_carrier_id' => ['nullable', 'integer', 'exists:shipping_carriers,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
