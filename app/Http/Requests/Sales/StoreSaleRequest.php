<?php

namespace App\Http\Requests\Sales;

use App\Support\ElSalvadorGeo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('customer_mode') === 'existing'),
                'exists:customers,id',
            ],
            'new_customer.name' => [
                Rule::requiredIf(fn () => $this->input('customer_mode') === 'new'),
                'nullable',
                'string',
                'max:255',
            ],
            'new_customer.document_type' => [
                Rule::requiredIf(fn () => $this->input('customer_mode') === 'new'),
                'nullable',
                'string',
                'max:20',
            ],
            'new_customer.document_number' => ['nullable', 'string', 'max:50'],
            'new_customer.email' => ['nullable', 'email', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:50'],
            'new_customer.address' => ['nullable', 'string'],
            'new_customer.department' => [
                Rule::requiredIf(fn () => $this->input('customer_mode') === 'new'),
                'nullable',
                'string',
                'max:100',
                Rule::in(ElSalvadorGeo::departmentNames()),
            ],
            'new_customer.municipality' => [
                Rule::requiredIf(fn () => $this->input('customer_mode') === 'new'),
                'nullable',
                'string',
                'max:100',
            ],
            'new_customer.country' => ['nullable', 'string', 'max:100'],
            'new_customer.postal_code' => ['nullable', 'string', 'max:20'],
            'new_customer.notes' => ['nullable', 'string'],
            'seller_id' => [
                'required',
                Rule::exists('sellers', 'id')->where('is_active', true),
            ],
            'sold_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(array_keys(config('sales.payment_methods')))],
            'has_shipping' => ['sometimes', 'boolean'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_carrier_id' => [
                Rule::requiredIf(fn () => $this->boolean('has_shipping')),
                'nullable',
                Rule::exists('shipping_carriers', 'id')->where('is_active', true),
            ],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_with_vat' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price_without_vat' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.combo_id' => ['nullable', 'exists:combos,id'],
            'combos' => ['nullable', 'array'],
            'combos.*.combo_id' => ['required', 'exists:combos,id'],
            'combos.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.required' => 'Cada línea debe tener un producto.',
            'customer_id.required' => 'Selecciona un cliente existente.',
            'seller_id.required' => 'Selecciona el vendedor.',
            'shipping_carrier_id.required' => 'Selecciona la empresa de envío.',
            'new_customer.name.required' => 'Indica el nombre del nuevo cliente.',
            'new_customer.department.required' => 'Selecciona el departamento del cliente.',
            'new_customer.municipality.required' => 'Selecciona el municipio del cliente.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasItems = collect($this->input('items', []))
                ->contains(fn ($row) => filled($row['product_id'] ?? null) && (int) ($row['quantity'] ?? 0) > 0);
            $hasCombos = collect($this->input('combos', []))
                ->contains(fn ($row) => filled($row['combo_id'] ?? null) && (int) ($row['quantity'] ?? 0) > 0);

            if (! $hasItems && ! $hasCombos) {
                $validator->errors()->add('items', 'Agrega al menos un producto o un combo a la venta.');
            }

            if ($this->input('customer_mode') !== 'new') {
                return;
            }

            $department = data_get($this->input('new_customer'), 'department');
            $municipality = data_get($this->input('new_customer'), 'municipality');

            if (filled($department) && filled($municipality) && ! ElSalvadorGeo::isValidPair($department, $municipality)) {
                $validator->errors()->add('new_customer.municipality', 'El municipio no pertenece al departamento seleccionado.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function (array $row) {
                $withVat = $row['unit_price_with_vat'] ?? null;
                if ($withVat === null && isset($row['unit_price_without_vat'])) {
                    $withVat = price_with_vat($row['unit_price_without_vat']);
                }

                return [
                    'product_id' => $row['product_id'] ?? null,
                    'quantity' => $row['quantity'] ?? null,
                    'unit_price_with_vat' => $withVat,
                    'unit_price_without_vat' => price_without_vat($withVat ?? 0),
                    'discount_percent' => $row['discount_percent'] ?? 0,
                    'discount_amount' => $row['discount_amount'] ?? 0,
                    'combo_id' => $row['combo_id'] ?? null,
                ];
            })
            ->filter(fn (array $row) => filled($row['product_id']))
            ->values()
            ->all();

        $combos = collect($this->input('combos', []))
            ->filter(fn ($row) => filled($row['combo_id'] ?? null) && (int) ($row['quantity'] ?? 0) > 0)
            ->map(fn ($row) => [
                'combo_id' => (int) $row['combo_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->values()
            ->all();

        $newCustomer = $this->input('new_customer', []);
        if (is_array($newCustomer)) {
            $department = trim((string) ($newCustomer['department'] ?? ''));
            $municipality = trim((string) ($newCustomer['municipality'] ?? ''));
            $postal = trim((string) ($newCustomer['postal_code'] ?? ''));

            if ($postal === '' && filled($department) && filled($municipality)) {
                $postal = ElSalvadorGeo::postalCodeFor($department, $municipality) ?? '';
            }

            $documentType = trim((string) ($newCustomer['document_type'] ?? 'N/A')) ?: 'N/A';
            $documentNumber = trim((string) ($newCustomer['document_number'] ?? ''));

            $newCustomer = array_merge($newCustomer, [
                'document_type' => $documentType,
                'document_number' => $documentType === 'N/A' ? null : ($documentNumber !== '' ? $documentNumber : null),
                'country' => trim((string) ($newCustomer['country'] ?? '')) ?: 'El Salvador',
                'department' => $department !== '' ? $department : null,
                'municipality' => $municipality !== '' ? $municipality : null,
                'postal_code' => $postal !== '' ? $postal : null,
            ]);
        }

        $this->merge([
            'customer_mode' => $this->input('customer_mode', 'new'),
            'items' => $items,
            'combos' => $combos,
            'has_shipping' => $this->boolean('has_shipping'),
            'shipping_amount' => $this->boolean('has_shipping')
                ? $this->input('shipping_amount', config('sales.default_shipping_amount', 3))
                : 0,
            'shipping_carrier_id' => $this->boolean('has_shipping')
                ? $this->input('shipping_carrier_id')
                : null,
            'discount_percent' => $this->input('discount_percent', 0),
            'discount_amount' => $this->input('discount_amount', 0),
            'new_customer' => $newCustomer,
        ]);
    }

    public function validated($key = null, $default = null): mixed
    {
        return parent::validated($key, $default);
    }
}
