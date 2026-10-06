<?php

namespace App\Http\Requests\Logistics;

use App\Models\LogisticsSettlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogisticsSettlementRequest extends FormRequest
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
            'logistics_client_id' => ['required', 'integer', 'exists:logistics_clients,id'],
            'period_type' => ['required', Rule::in([
                LogisticsSettlement::PERIOD_WEEK,
                LogisticsSettlement::PERIOD_MONTH,
                LogisticsSettlement::PERIOD_CUSTOM,
            ])],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
