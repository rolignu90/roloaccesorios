<?php

namespace App\Http\Requests\Consignments;

use App\Models\Consignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConsignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_type' => ['required', Rule::in([Consignment::PARTY_SELLER, Consignment::PARTY_CUSTOMER])],
            'seller_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('party_type') === Consignment::PARTY_SELLER),
                'exists:sellers,id',
            ],
            'customer_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('party_type') === Consignment::PARTY_CUSTOMER),
                'exists:customers,id',
            ],
            'delivered_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_with_vat' => ['required', 'numeric', 'min:0'],
            'items.*.price_manual' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'seller_id.required' => 'Selecciona el vendedor.',
            'customer_id.required' => 'Selecciona el cliente.',
            'items.required' => 'Agrega al menos un producto.',
            'items.*.product_id.distinct' => 'No repitas el mismo producto; suma cantidades en una sola línea.',
            'items.*.unit_price_with_vat.required' => 'Indica el precio de consignación c/IVA.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('party_type') === Consignment::PARTY_SELLER && $this->filled('customer_id')) {
                $validator->errors()->add('customer_id', 'No combines vendedor y cliente.');
            }
            if ($this->input('party_type') === Consignment::PARTY_CUSTOMER && $this->filled('seller_id')) {
                $validator->errors()->add('seller_id', 'No combines vendedor y cliente.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $partyType = $this->input('party_type');

        $this->merge([
            'seller_id' => $partyType === Consignment::PARTY_SELLER ? $this->input('seller_id') : null,
            'customer_id' => $partyType === Consignment::PARTY_CUSTOMER ? $this->input('customer_id') : null,
        ]);
    }
}
