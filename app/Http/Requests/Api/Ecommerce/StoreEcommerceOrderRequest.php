<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Support\ElSalvadorGeo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEcommerceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_order_id' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(['cash', 'transfer'])],
            'has_shipping' => ['sometimes', 'boolean'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_carrier_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('has_shipping', true)),
                Rule::exists('shipping_carriers', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:50'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.address' => ['nullable', 'string'],
            'customer.department' => ['nullable', 'string', 'max:100', Rule::in(ElSalvadorGeo::departmentNames())],
            'customer.municipality' => ['nullable', 'string', 'max:100'],
            'customer.country' => ['nullable', 'string', 'max:100'],
            'customer.postal_code' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_code' => ['nullable', 'string', 'max:100'],
            'items.*.combo_code' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_order_id.required' => 'Indica external_order_id (idempotencia).',
            'payment_method.in' => 'payment_method debe ser cash o transfer.',
            'customer.phone.required' => 'El teléfono del cliente es obligatorio.',
            'items.required' => 'Agrega al menos un ítem.',
            'shipping_carrier_id.required' => 'Selecciona shipping_carrier_id cuando has_shipping=true.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $hasProduct = filled($item['product_code'] ?? null);
                $hasCombo = filled($item['combo_code'] ?? null);
                if ($hasProduct === $hasCombo) {
                    $validator->errors()->add(
                        "items.$index",
                        'Cada ítem debe tener product_code o combo_code (uno solo).'
                    );
                }
            }

            $department = data_get($this->input('customer'), 'department');
            $municipality = data_get($this->input('customer'), 'municipality');
            if (filled($department) && filled($municipality) && ! ElSalvadorGeo::isValidPair($department, $municipality)) {
                $validator->errors()->add('customer.municipality', 'Municipio inválido para el departamento.');
            }
        });
    }
}
