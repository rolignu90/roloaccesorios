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
        ];
    }

    public function messages(): array
    {
        return [
            'sale_prefix.required' => 'Indica el prefijo de las ventas de este vendedor (ej. M-).',
            'sale_prefix.regex' => 'El prefijo solo puede tener letras/números (ej. M o M-).',
            'sale_prefix.unique' => 'Ese prefijo ya lo usa otro vendedor.',
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
        ]);
    }
}
