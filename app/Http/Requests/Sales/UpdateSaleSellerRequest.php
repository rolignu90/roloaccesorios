<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seller_id' => [
                'required',
                Rule::exists('sellers', 'id')->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'seller_id.required' => 'Selecciona el vendedor.',
            'seller_id.exists' => 'El vendedor no existe o no está activo.',
        ];
    }
}
