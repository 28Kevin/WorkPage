<?php

namespace App\Http\Requests;

use App\Enums\ExamType;
use App\Services\IdealWeightCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // A. Datos del trabajador
            'full_name' => ['required', 'string', 'min:5', 'max:150'],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1920-01-01'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'height_cm' => ['required', 'integer', 'min:120', 'max:230'],
            'weight_kg' => ['nullable', 'numeric', 'min:30', 'max:250'],

            // B. Datos ocupacionales y de afiliacion
            'company_name' => ['required', 'string', 'max:150'],
            'company_nit' => ['required', 'string', 'max:30'],
            'eps_id' => ['required', 'integer', Rule::exists('eps', 'id')->where('active', true)],
            'arl_id' => ['required', 'integer', Rule::exists('arls', 'id')->where('active', true)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'position' => ['required', 'string', 'max:150'],
            'risk_ids' => ['required', 'array', 'min:1'],
            'risk_ids.*' => ['integer', Rule::exists('risks', 'id')->where('active', true)],

            // C. Detalles del examen
            'exam_date' => ['required', 'date', 'before_or_equal:today'],
            'exam_type' => ['required', Rule::enum(ExamType::class)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'document_number' => 'número de cédula',
            'birth_date' => 'fecha de nacimiento',
            'email' => 'correo electrónico',
            'phone' => 'número de celular',
            'height_cm' => 'estatura',
            'weight_kg' => 'peso',
            'company_name' => 'empresa',
            'company_nit' => 'NIT',
            'eps_id' => 'EPS',
            'arl_id' => 'ARL',
            'city_id' => 'ciudad',
            'position' => 'cargo',
            'risk_ids' => 'riesgos/especialidades',
            'exam_date' => 'fecha del examen',
            'exam_type' => 'tipo de examen',
        ];
    }

    /** El peso se autocalcula desde la estatura cuando no se envia. */
    public function validatedWithDefaults(IdealWeightCalculator $calculator): array
    {
        $data = $this->validated();

        if (blank($data['weight_kg'] ?? null)) {
            $data['weight_kg'] = $calculator->forHeight((int) $data['height_cm'])['ideal_weight_kg'];
        }

        return $data;
    }
}
