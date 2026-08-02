<?php

namespace App\Http\Requests\Sales;

use App\Support\ElSalvadorGeo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSaleCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:100', Rule::in(ElSalvadorGeo::departmentNames())],
            'municipality' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
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

        if ($postal === '' && filled($department) && filled($municipality)) {
            $postal = ElSalvadorGeo::postalCodeFor($department, $municipality) ?? '';
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => trim((string) $this->input('phone')) ?: null,
            'email' => trim((string) $this->input('email')) ?: null,
            'address' => trim((string) $this->input('address')) ?: null,
            'country' => trim((string) ($this->input('country') ?: 'El Salvador')),
            'department' => $department !== '' ? $department : null,
            'municipality' => $municipality !== '' ? $municipality : null,
            'postal_code' => $postal !== '' ? $postal : null,
        ]);
    }
}
