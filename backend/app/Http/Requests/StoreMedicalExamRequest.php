<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\ExamResult;
use App\Enums\ExamType;
use App\Enums\Sex;
use App\Services\IdealWeightCalculator;
use App\Support\ExamForm;
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
        $systemKeys = implode(',', array_keys(ExamForm::SYSTEMS));
        $paraclinicalKeys = implode(',', array_keys(ExamForm::PARACLINICALS));
        $assessmentKeys = implode(',', array_keys(ExamForm::ASSESSMENTS));

        return [
            // A. Identificacion del trabajador
            'full_name' => ['required', 'string', 'min:5', 'max:150'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1920-01-01'],
            'sex' => ['required', Rule::enum(Sex::class)],
            // Fotografia opcional: ~400 KB de base64 tras reducirla en el navegador.
            'photo' => ['nullable', 'string', 'max:400000', 'regex:/^data:image\/(png|jpeg|webp);base64,/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'height_cm' => ['required', 'integer', 'min:120', 'max:230'],
            'weight_kg' => ['nullable', 'numeric', 'min:30', 'max:250'],
            'eps_id' => ['nullable', 'integer', Rule::exists('eps', 'id')->where('active', true)],
            'arl_id' => ['required', 'integer', Rule::exists('arls', 'id')->where('active', true)],
            'afp_id' => ['nullable', 'integer', Rule::exists('afps', 'id')->where('active', true)],

            // B. Empleador
            'is_independent' => ['required', 'boolean'],
            'company_name' => ['required', 'string', 'max:150'],
            // Un trabajador independiente no tiene NIT que registrar.
            'company_nit' => [
                Rule::requiredIf(fn () => ! $this->boolean('is_independent')),
                'nullable', 'string', 'max:30',
            ],
            'client_company' => ['nullable', 'string', 'max:150'],
            'economic_activity' => ['nullable', 'string', 'max:150'],
            'position' => ['required', 'string', 'max:150'],

            // C. Datos de la evaluacion
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('active', true)],
            'exam_date' => ['required', 'date', 'before_or_equal:today'],
            'exam_type' => ['required', Rule::enum(ExamType::class)],

            // D. Examen fisico (precargado, editable)
            'vitals' => ['nullable', 'array:systolic,diastolic,heart_rate,respiratory_rate,temperature,spo2'],
            'vitals.systolic' => ['nullable', 'integer', 'min:70', 'max:220'],
            'vitals.diastolic' => ['nullable', 'integer', 'min:40', 'max:140'],
            'vitals.heart_rate' => ['nullable', 'integer', 'min:35', 'max:180'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'min:8', 'max:40'],
            'vitals.temperature' => ['nullable', 'numeric', 'min:34', 'max:42'],
            'vitals.spo2' => ['nullable', 'integer', 'min:70', 'max:100'],

            'vision' => ['nullable', 'array:right_eye,left_eye,optical_correction'],
            'vision.right_eye' => ['nullable', 'string', 'max:20'],
            'vision.left_eye' => ['nullable', 'string', 'max:20'],
            'vision.optical_correction' => ['nullable', 'boolean'],

            'systems' => ['nullable', 'array:'.$systemKeys],
            'systems.*' => ['required', Rule::in([ExamForm::NORMAL, ExamForm::FINDINGS])],

            'assessments' => ['nullable', 'array:'.$assessmentKeys],
            'assessments.*' => ['nullable', 'string', 'max:500'],

            'clinical_findings' => ['nullable', 'string', 'max:2000'],

            // E. Paraclinicos
            'paraclinicals' => ['nullable', 'array:'.$paraclinicalKeys],
            'paraclinicals.*.performed' => ['required', 'boolean'],
            'paraclinicals.*.status' => ['required', Rule::in([ExamForm::NORMAL, ExamForm::ALTERED])],
            'paraclinicals.*.result' => ['nullable', 'string', 'max:255'],

            // F. Concepto de aptitud: el formato siempre cubre los tres frentes.
            'aptitude_position' => ['required', Rule::enum(ExamResult::class)],
            'aptitude_heights' => ['required', Rule::enum(ExamResult::class)],
            'aptitude_confined' => ['required', Rule::enum(ExamResult::class)],
            'restrictions' => ['nullable', 'string', 'max:2000'],
            'restrictions_validity' => ['nullable', 'string', 'max:120'],

            // G. Consentimiento informado
            'consent_accepted' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'photo' => 'fotografía',
            'height_cm' => 'estatura',
            'weight_kg' => 'peso',
            'is_independent' => 'trabajador independiente',
            'company_name' => 'empresa',
            'company_nit' => 'NIT',
            'client_company' => 'empresa usuaria',
            'economic_activity' => 'actividad económica',
            'eps_id' => 'EPS',
            'arl_id' => 'ARL',
            'afp_id' => 'AFP',
            'city_id' => 'ciudad',
            'position' => 'cargo',
            'exam_date' => 'fecha de evaluación',
            'exam_type' => 'tipo de evaluación',
            'clinical_findings' => 'hallazgos clínicos',
            'aptitude_position' => 'concepto para el cargo',
            'aptitude_heights' => 'concepto para trabajo en alturas',
            'aptitude_confined' => 'concepto para espacios confinados',
            'restrictions' => 'recomendaciones y restricciones',
            'restrictions_validity' => 'temporalidad de las restricciones',
            'consent_accepted' => 'consentimiento informado',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'consent_accepted.required' => 'Debe registrar si el trabajador acepta o niega el consentimiento.',
            'company_nit.required' => 'Indique el NIT de la empresa o marque al trabajador como independiente.',
            'photo.regex' => 'La fotografía debe ser una imagen PNG, JPG o WEBP.',
            'photo.max' => 'La fotografía supera el tamaño permitido. Use una imagen más liviana.',
        ];
    }

    /** El peso se autocalcula desde la estatura cuando no se envia. */
    public function validatedWithDefaults(IdealWeightCalculator $calculator): array
    {
        $data = $this->validated();

        if (blank($data['weight_kg'] ?? null)) {
            $data['weight_kg'] = $calculator->forHeight((int) $data['height_cm'])['ideal_weight_kg'];
        }

        // Un independiente nunca guarda NIT, aunque venga en la peticion.
        if ($data['is_independent'] ?? false) {
            $data['company_nit'] = null;
        }

        return $data;
    }
}
