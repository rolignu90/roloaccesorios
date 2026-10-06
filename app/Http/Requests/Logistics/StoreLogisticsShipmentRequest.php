<?php

namespace App\Http\Requests\Logistics;

use App\Support\ElSalvadorGeo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogisticsShipmentRequest extends FormRequest
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
        $department = $this->input('department');

        return [
            'logistics_client_id' => ['required', 'integer', 'exists:logistics_clients,id'],
            'shipping_carrier_id' => ['nullable', 'integer', 'exists:shipping_carriers,id'],
            'shipped_at' => ['nullable', 'date'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'recipient_address' => ['required', 'string', 'max:500'],
            'department' => ['required', 'string', Rule::in(ElSalvadorGeo::departmentNames())],
            'municipality' => [
                'required',
                'string',
                Rule::in(filled($department) ? ElSalvadorGeo::municipalityNames($department) : []),
            ],
            'country' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'collect_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'send_to_sistrack' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_to_sistrack' => $this->boolean('send_to_sistrack'),
        ]);
    }
}
