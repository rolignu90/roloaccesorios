<?php

namespace App\Http\Requests\Sales;

use App\Models\SellerSettlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellerSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'period_type' => ['required', Rule::in([
                SellerSettlement::PERIOD_WEEK,
                SellerSettlement::PERIOD_MONTH,
                SellerSettlement::PERIOD_CUSTOM,
            ])],
            'anchor_date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'apply_to_consignments' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'seller_id.required' => 'Selecciona el vendedor.',
            'period_type.required' => 'Selecciona el tipo de período.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => trim((string) $this->input('notes')) ?: null,
            'payment_method' => trim((string) $this->input('payment_method')) ?: null,
        ]);
    }
}
