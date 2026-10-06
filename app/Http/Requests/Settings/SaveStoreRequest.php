<?php

namespace App\Http\Requests\Settings;

use App\Models\Seller;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120', Rule::unique('stores', 'name')->ignore($this->route('store'))],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'ticket_footer' => ['nullable', 'string', 'max:255'],
            'seller_id' => ['nullable', 'integer', Rule::exists('sellers', 'id')],
            'ticket_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+-?$/'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('seller_id') && ! $this->filled('ticket_prefix')) {
                $validator->errors()->add('seller_id', 'Elige el vendedor de la tienda o indica un prefijo para crear uno nuevo.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una tienda con ese nombre.',
            'ticket_prefix.regex' => 'El prefijo solo puede tener letras/números (ej. T2 o T2-).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'ticket_prefix' => Seller::normalizeSalePrefix($this->input('ticket_prefix')),
        ]);
    }
}
