<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        // Solo nombres de familias: se usan para armar la URL de Google Fonts.
        $font = ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9 ]+$/'];

        return [
            'identity' => ['required', 'array'],
            'identity.app_name' => ['required', 'string', 'max:80'],
            'identity.tagline' => ['nullable', 'string', 'max:120'],
            // ~700 KB de base64 equivalen a una imagen de unos 500 KB.
            'identity.logo' => ['nullable', 'string', 'max:700000', 'regex:/^data:image\/(png|jpeg|webp|svg\+xml);base64,/'],

            'theme' => ['required', 'array'],
            'theme.brand_color' => $hex,
            'theme.accent_color' => $hex,
            'theme.font_heading' => $font,
            'theme.font_body' => $font,
            'theme.radius' => ['required', 'string', 'in:0rem,0.25rem,0.5rem,0.75rem,1rem,1.5rem'],

            'center' => ['required', 'array'],
            'center.name' => ['required', 'string', 'max:120'],
            'center.nit' => ['required', 'string', 'max:40'],
            'center.address' => ['required', 'string', 'max:160'],
            'center.phone' => ['required', 'string', 'max:40'],
            'center.email' => ['required', 'email', 'max:120'],
            'center.physician_name' => ['nullable', 'string', 'max:120'],
            'center.schedule' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return [
            'identity.app_name' => 'nombre de la plataforma',
            'identity.tagline' => 'eslogan',
            'identity.logo' => 'logo',
            'theme.brand_color' => 'color de marca',
            'theme.accent_color' => 'color de acento',
            'theme.font_heading' => 'tipografía de títulos',
            'theme.font_body' => 'tipografía de texto',
            'theme.radius' => 'redondeo de bordes',
            'center.name' => 'razón social',
            'center.nit' => 'NIT',
            'center.address' => 'dirección',
            'center.phone' => 'teléfono',
            'center.email' => 'correo electrónico',
            'center.physician_name' => 'médico responsable',
            'center.schedule' => 'horario de atención',
        ];
    }

    public function messages(): array
    {
        return [
            'theme.brand_color.regex' => 'El color debe estar en formato hexadecimal, por ejemplo #2563eb.',
            'theme.accent_color.regex' => 'El color debe estar en formato hexadecimal, por ejemplo #0284c7.',
            'theme.font_heading.regex' => 'Escriba solo el nombre de la familia tipográfica, sin comillas ni comas.',
            'theme.font_body.regex' => 'Escriba solo el nombre de la familia tipográfica, sin comillas ni comas.',
            'identity.logo.regex' => 'El logo debe ser una imagen PNG, JPG, WEBP o SVG.',
            'identity.logo.max' => 'El logo supera el tamaño permitido. Use una imagen más liviana.',
        ];
    }
}
