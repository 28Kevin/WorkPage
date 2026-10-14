<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'issue_place' => ['required', 'string', 'max:80'],
            'issue_date' => ['required', 'date'],

            'company_name' => ['required', 'string', 'max:150'],
            'company_nit' => ['required', 'string', 'max:40'],
            'company_address' => ['required', 'string', 'max:180'],
            'company_city' => ['nullable', 'string', 'max:80'],

            // Al menos un item: una cuenta sin conceptos no cobra nada.
            'items' => ['required', 'array', 'min:1'],
            'items.*.concept' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            // En pesos enteros: el documento no maneja centavos.
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:999999999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'issue_place' => 'lugar de expedición',
            'issue_date' => 'fecha de expedición',
            'company_name' => 'razón social',
            'company_nit' => 'NIT de la empresa',
            'company_address' => 'dirección',
            'company_city' => 'ciudad',
            'items' => 'ítems',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Agregue al menos un ítem a la cuenta de cobro.',
            'items.min' => 'Agregue al menos un ítem a la cuenta de cobro.',
            'items.*.concept.required' => 'Elija el concepto del ítem.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'items.*.unit_price.required' => 'Indique el precio unitario del ítem.',
        ];
    }
}
