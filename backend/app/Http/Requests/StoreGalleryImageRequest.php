<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'caption' => ['nullable', 'string', 'max:200'],
            // ~1,4 MB de base64: el navegador la reduce antes de enviarla.
            'image' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string', 'max:1400000',
                'regex:/^data:image\/(png|jpeg|webp);base64,/',
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'caption' => 'descripción',
            'image' => 'imagen',
            'position' => 'orden',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.regex' => 'La imagen debe estar en formato PNG, JPG o WEBP.',
            'image.max' => 'La imagen supera el tamaño permitido. Use una más liviana.',
        ];
    }
}
