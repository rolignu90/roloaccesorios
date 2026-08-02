<?php

namespace App\Http\Requests\Sales;

use App\Support\ElSalvadorGeo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:20'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100', Rule::in(ElSalvadorGeo::departmentNames())],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $department = $this->input('department');
            $municipality = $this->input('municipality');

            if (filled($department) && filled($municipality) && ! ElSalvadorGeo::isValidPair($department, $municipality)) {
                $validator->errors()->add('municipality', 'El municipio no pertenece al departamento seleccionado.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $department = trim((string) $this->input('department'));
        $municipality = trim((string) $this->input('municipality'));
        $postal = trim((string) $this->input('postal_code'));
        $documentType = trim((string) ($this->input('document_type') ?: 'N/A'));
        $documentNumber = trim((string) $this->input('document_number'));

        if ($postal === '' && filled($department) && filled($municipality)) {
            $postal = ElSalvadorGeo::postalCodeFor($department, $municipality) ?? '';
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'country' => trim((string) ($this->input('country') ?: 'El Salvador')),
            'department' => $department !== '' ? $department : null,
            'municipality' => $municipality !== '' ? $municipality : null,
            'postal_code' => $postal !== '' ? $postal : null,
            'document_type' => $documentType !== '' ? $documentType : 'N/A',
            'document_number' => $documentType === 'N/A' ? null : ($documentNumber !== '' ? $documentNumber : null),
        ]);
    }
}
