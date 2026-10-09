<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Lo que se imprime en el certificado.
            'participant_name' => ['required', 'string', 'min:5', 'max:150'],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'course' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['required', 'integer', 'min:1', 'max:1000'],
            'signed_date' => ['required', 'date', 'after_or_equal:end_date'],

            // Solo para la pagina publica a la que lleva el QR.
            'company_nit' => ['nullable', 'string', 'max:40'],
            'company_logo' => [
                'nullable', 'string', 'max:700000',
                'regex:/^data:image\/(png|jpeg|webp|svg\+xml);base64,/',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'participant_name' => 'nombre del participante',
            'document_number' => 'número de documento',
            'course' => 'curso',
            'city' => 'ciudad',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de finalización',
            'hours' => 'intensidad horaria',
            'signed_date' => 'fecha de firma',
            'company_nit' => 'NIT de la empresa',
            'company_logo' => 'logo de la empresa',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_number.regex' => 'El número de documento debe contener solo dígitos.',
            'end_date.after_or_equal' => 'La fecha de finalización no puede ser anterior a la de inicio.',
            'signed_date.after_or_equal' => 'La firma no puede ser anterior a la finalización del curso.',
            'company_logo.regex' => 'El logo debe ser una imagen PNG, JPG, WEBP o SVG.',
            'company_logo.max' => 'El logo supera el tamaño permitido. Use una imagen más liviana.',
        ];
    }
}
