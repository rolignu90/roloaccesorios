<?php

namespace App\Http\Requests\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'supplier_id' => [
                'required',
                'exists:suppliers,id',
                Rule::exists('product_suppliers', 'supplier_id')->where(
                    fn ($query) => $query->where('product_id', $this->input('product_id'))
                ),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'received_at' => ['required', 'date'],
            'lot_number' => ['nullable', 'string', 'max:80', 'unique:inventory_lots,lot_number'],
            'invoice_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'El proveedor seleccionado no está asociado a este producto.',
            'supplier_id.required' => 'Debes elegir un proveedor de compra para el producto.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('product_id')) {
                return;
            }

            $product = Product::query()->with('productSuppliers')->find($this->input('product_id'));

            if ($product && $product->productSuppliers->isEmpty()) {
                $validator->errors()->add(
                    'supplier_id',
                    'Este producto no tiene proveedores. Agrégalos en la ficha del producto.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('lot_number')) {
            $this->merge([
                'lot_number' => strtoupper(trim((string) $this->input('lot_number'))),
            ]);
        }
    }
}
