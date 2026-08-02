<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'string', 'max:30'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'sale_price_with_vat' => ['required', 'numeric', 'min:0'],
            'wholesale_price_with_vat' => ['nullable', 'numeric', 'min:0'],
            'promo_active' => ['sometimes', 'boolean'],
            'promo_type' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('promo_active')),
                Rule::in([Product::PROMO_AMOUNT, Product::PROMO_PERCENT]),
            ],
            'promo_value' => [
                'nullable',
                Rule::requiredIf(fn () => $this->boolean('promo_active')),
                'numeric',
                'min:0',
            ],
            'min_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'free_shipping' => ['sometimes', 'boolean'],
            'suppliers' => ['nullable', 'array'],
            'suppliers.*.supplier_id' => ['nullable', 'distinct', 'exists:suppliers,id'],
            'suppliers.*.purchase_price' => ['nullable', 'numeric', 'min:0', 'required_with:suppliers.*.supplier_id'],
            'suppliers.*.is_preferred' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'suppliers.*.supplier_id.distinct' => 'No puedes repetir el mismo proveedor en un producto.',
            'suppliers.*.purchase_price.required_with' => 'Cada proveedor debe tener su precio de compra en USD.',
            'sale_price_with_vat.required' => 'Indica el precio de venta con IVA.',
            'promo_type.required' => 'Elige si la promo es por monto o porcentaje.',
            'promo_value.required' => 'Indica el valor de la promoción.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('promo_active')) {
                return;
            }

            if ($this->input('promo_type') === Product::PROMO_PERCENT && (float) $this->input('promo_value') > 100) {
                $validator->errors()->add('promo_value', 'El porcentaje de promoción no puede ser mayor a 100.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $suppliers = collect($this->input('suppliers', []))
            ->map(function (array $row) {
                return [
                    'supplier_id' => $row['supplier_id'] ?? null,
                    'purchase_price' => $row['purchase_price'] ?? null,
                    'is_preferred' => ! empty($row['is_preferred']),
                ];
            })
            ->filter(fn (array $row) => filled($row['supplier_id']))
            ->values()
            ->all();

        $promoActive = $this->boolean('promo_active');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'promo_active' => $promoActive,
            'free_shipping' => $this->boolean('free_shipping'),
            'promo_type' => $promoActive ? $this->input('promo_type') : null,
            'promo_value' => $promoActive ? $this->input('promo_value') : null,
            'code' => strtoupper(trim((string) $this->input('code'))),
            'suppliers' => $suppliers,
        ]);
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        unset($data['sale_price_with_vat'], $data['wholesale_price_with_vat']);
        $data['sale_price_without_vat'] = price_without_vat($this->input('sale_price_with_vat', 0));
        $wholesale = $this->input('wholesale_price_with_vat');
        $data['wholesale_price_without_vat'] = ($wholesale === null || $wholesale === '')
            ? null
            : price_without_vat($wholesale);

        if (! ($data['promo_active'] ?? false)) {
            $data['promo_type'] = null;
            $data['promo_value'] = null;
            $data['promo_active'] = false;
        }

        return $data;
    }
}
