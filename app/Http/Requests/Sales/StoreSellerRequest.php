<?php

namespace App\Http\Requests\Sales;

use App\Models\Seller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sale_prefix' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+-$/',
                Rule::unique('sellers', 'sale_prefix'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'type' => ['required', Rule::in([Seller::TYPE_EXTERNAL, Seller::TYPE_INTERNAL])],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
                Rule::unique('sellers', 'customer_id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sale_prefix.required' => 'Indica el prefijo de las ventas de este vendedor (ej. M-).',
            'sale_prefix.regex' => 'El prefijo solo puede tener letras/números (ej. M o M-).',
            'sale_prefix.unique' => 'Ese prefijo ya lo usa otro vendedor.',
            'type.required' => 'Indica si el vendedor es interno o externo.',
            'customer_id.unique' => 'Ese cliente ya está vinculado a otro vendedor.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'name' => trim((string) $this->input('name')),
            'sale_prefix' => Seller::normalizeSalePrefix($this->input('sale_prefix')),
            'phone' => trim((string) $this->input('phone')) ?: null,
            'email' => trim((string) $this->input('email')) ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
            'type' => $this->input('type', Seller::TYPE_EXTERNAL),
            'salary_amount' => $this->filled('salary_amount') ? $this->input('salary_amount') : null,
            'commission_percent' => $this->filled('commission_percent') ? $this->input('commission_percent') : null,
            'customer_id' => $this->filled('customer_id') ? $this->input('customer_id') : null,
        ]);
    }
}
