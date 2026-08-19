<?php

namespace App\Http\Requests\Sales;

use App\Models\ShippingCarrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30', 'unique:shipping_carriers,code'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'commission_type' => ['required', Rule::in(array_keys(ShippingCarrier::COMMISSION_TYPES))],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sistrack_enabled' => ['sometimes', 'boolean'],
            'sistrack_base_url' => ['nullable', 'url', 'max:255'],
            'sistrack_email' => ['nullable', 'email', 'max:255'],
            'sistrack_password' => ['nullable', 'string', 'max:255'],
            'sistrack_sender_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('sistrack_enabled')) {
                return;
            }

            if (! filled($this->input('sistrack_email'))) {
                $validator->errors()->add('sistrack_email', 'El correo Sistrack es obligatorio si está activado.');
            }
            if (! filled($this->input('sistrack_password'))) {
                $validator->errors()->add('sistrack_password', 'La contraseña Sistrack es obligatoria si está activado.');
            }
            if (! filled($this->input('sistrack_base_url'))) {
                $validator->errors()->add('sistrack_base_url', 'La URL Sistrack es obligatoria si está activado.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'sistrack_enabled' => $this->boolean('sistrack_enabled'),
            'code' => filled($this->input('code')) ? strtoupper(trim((string) $this->input('code'))) : null,
            'sistrack_base_url' => filled($this->input('sistrack_base_url'))
                ? rtrim(trim((string) $this->input('sistrack_base_url')), '/')
                : null,
            'sistrack_sender_id' => filled($this->input('sistrack_sender_id'))
                ? (int) $this->input('sistrack_sender_id')
                : null,
        ]);
    }
}
