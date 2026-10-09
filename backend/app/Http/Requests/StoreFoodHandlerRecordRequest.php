<?php

namespace App\Http\Requests;

use App\Support\FoodHandlerForm;
use Illuminate\Foundation\Http\FormRequest;

class StoreFoodHandlerRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Datos del paciente: se escriben una vez y llenan las cinco paginas.
            'issue_date' => ['required', 'date', 'after_or_equal:2020-01-01'],
            'full_name' => ['required', 'string', 'min:5', 'max:150'],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'age' => ['required', 'integer', 'min:14', 'max:100'],
            // La foto va al carnet; ~400 KB de base64 tras reducirla en el navegador.
            'photo' => ['nullable', 'string', 'max:400000', 'regex:/^data:image\/(png|jpeg|webp);base64,/'],

            // Resultados de laboratorio: las claves salen del catalogo del formato.
            ...FoodHandlerForm::validationRules(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'issue_date' => 'fecha del documento',
            'full_name' => 'nombre completo',
            'document_number' => 'número de documento',
            'age' => 'edad',
            'photo' => 'fotografía',
            'lab_results' => 'resultados de laboratorio',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_number.regex' => 'El número de documento debe contener solo dígitos.',
            'photo.regex' => 'La fotografía debe ser una imagen PNG, JPG o WEBP.',
            'photo.max' => 'La fotografía supera el tamaño permitido. Use una imagen más liviana.',
        ];
    }
}
