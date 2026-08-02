<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryLot;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var InventoryLot $lot */
        $lot = $this->route('lot');
        $unused = (int) $lot->quantity_remaining === (int) $lot->quantity_received;

        if ($unused) {
            return [
                'supplier_id' => [
                    'required',
                    'exists:suppliers,id',
                    Rule::exists('product_suppliers', 'supplier_id')->where(
                        fn ($query) => $query->where('product_id', $lot->product_id)
                    ),
                ],
                'quantity' => ['required', 'integer', 'min:1'],
                'purchase_price' => ['required', 'numeric', 'min:0'],
                'received_at' => ['required', 'date'],
                'invoice_reference' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string'],
            ];
        }

        return [
            'quantity_remaining' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'El proveedor seleccionado no está asociado a este producto.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var InventoryLot $lot */
            $lot = $this->route('lot');
            $unused = (int) $lot->quantity_remaining === (int) $lot->quantity_received;

            if ($unused) {
                $product = Product::query()->with('productSuppliers')->find($lot->product_id);
                if ($product && $product->productSuppliers->isEmpty()) {
                    $validator->errors()->add('supplier_id', 'Este producto no tiene proveedores.');
                }
            }
        });
    }
}
