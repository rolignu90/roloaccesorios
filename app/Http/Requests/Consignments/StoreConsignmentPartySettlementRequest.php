<?php

namespace App\Http\Requests\Consignments;

use App\Models\Consignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConsignmentPartySettlementRequest extends FormRequest
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
                'integer',
                Rule::requiredIf(fn () => $this->input('party_type') === Consignment::PARTY_SELLER),
                'exists:sellers,id',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('party_type') === Consignment::PARTY_CUSTOMER),
                'exists:customers,id',
            ],
            'paid_at' => ['required', 'date'],
            'method' => ['required', Rule::in(array_keys(config('sales.payment_methods')))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.consignment_id' => ['required', 'integer', 'exists:consignments,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.selected' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rows = collect($this->input('allocations', []))
                ->filter(function ($row) {
                    $selected = $row['selected'] ?? null;
                    if ($selected === null) {
                        return (float) ($row['amount'] ?? 0) > 0.009;
                    }

                    return in_array($selected, [1, '1', true, 'on', 'yes'], true)
                        && (float) ($row['amount'] ?? 0) > 0.009;
                });

            if ($rows->isEmpty()) {
                $validator->errors()->add('allocations', 'Selecciona al menos una entrega con monto mayor a 0.');
            }
        });
    }

    /**
     * @return array{
     *     party_type: string,
     *     party_id: int,
     *     paid_at: string,
     *     method: string,
     *     notes: ?string,
     *     allocations: list<array{consignment_id: int, amount: float}>
     * }
     */
    public function settlementPayload(): array
    {
        $partyType = (string) $this->input('party_type');
        $partyId = $partyType === Consignment::PARTY_SELLER
            ? (int) $this->input('seller_id')
            : (int) $this->input('customer_id');

        $allocations = collect($this->input('allocations', []))
            ->filter(function ($row) {
                $selected = $row['selected'] ?? null;
                if ($selected === null) {
                    return (float) ($row['amount'] ?? 0) > 0.009;
                }

                return in_array($selected, [1, '1', true, 'on', 'yes'], true)
                    && (float) ($row['amount'] ?? 0) > 0.009;
            })
            ->map(fn ($row) => [
                'consignment_id' => (int) $row['consignment_id'],
                'amount' => round((float) $row['amount'], 2),
            ])
            ->values()
            ->all();

        return [
            'party_type' => $partyType,
            'party_id' => $partyId,
            'paid_at' => (string) $this->input('paid_at'),
            'method' => (string) $this->input('method'),
            'notes' => $this->input('notes'),
            'allocations' => $allocations,
        ];
    }
}
