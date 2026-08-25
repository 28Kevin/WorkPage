<?php

namespace App\Services;

use App\Enums\ExamResult;
use App\Models\MedicalExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orquesta la creacion y correccion de un examen: numeracion consecutiva,
 * calculo de peso adecuado, precarga de parametros medicos y codigo de
 * verificacion publica. Todo lo precargado se puede sobreescribir.
 */
class ExamGenerator
{
    /** Bloques del examen fisico que el formulario puede sobreescribir. */
    private const OVERRIDABLE = ['vitals', 'vision', 'systems', 'assessments'];

    private const RELATIONS = ['eps', 'arl', 'afp', 'city', 'risks', 'creator'];

    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly IdealWeightCalculator $weights,
        private readonly MedicalParameterGenerator $parameters,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null): MedicalExam
    {
        return DB::transaction(function () use ($data, $user) {
            $orderNumber = $this->orderNumbers->next();

            $exam = MedicalExam::create([
                ...$this->attributes($data),
                'order_number' => $orderNumber,
                'order_code' => $this->orderNumbers->format(
                    $orderNumber,
                    (int) date('Y', strtotime($data['exam_date'])),
                ),
                'verification_code' => $this->verificationCode(),
                'issued_at' => now(),
                'created_by' => $user?->id,
            ]);

            $exam->risks()->sync($data['risk_ids'] ?? []);

            return $exam->load(self::RELATIONS);
        });
    }

    /**
     * Corrige un examen conservando consecutivo, codigo de verificacion y
     * fecha de expedicion.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MedicalExam $exam, array $data): MedicalExam
    {
        return DB::transaction(function () use ($exam, $data) {
            $exam->update($this->attributes($data));

            if (array_key_exists('risk_ids', $data)) {
                $exam->risks()->sync($data['risk_ids']);
            }

            return $exam->fresh(self::RELATIONS);
        });
    }

    /**
     * Campos comunes a crear y corregir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $heightCm = (int) $data['height_cm'];
        $reference = $this->weights->forHeight($heightCm);
        $weightKg = isset($data['weight_kg'])
            ? (float) $data['weight_kg']
            : $reference['ideal_weight_kg'];

        $independent = (bool) ($data['is_independent'] ?? false);

        $aptitudes = [
            'aptitude_position' => $data['aptitude_position'] ?? ExamResult::Apto->value,
            'aptitude_heights' => $data['aptitude_heights'] ?? null,
            'aptitude_confined' => $data['aptitude_confined'] ?? null,
        ];

        return [
            'full_name' => $data['full_name'],
            'document_type' => $data['document_type'] ?? 'CC',
            'document_number' => $data['document_number'],
            'birth_date' => $data['birth_date'],
            'sex' => $data['sex'] ?? null,
            'photo' => $data['photo'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'height_cm' => $heightCm,
            'ideal_weight_kg' => $reference['ideal_weight_kg'],
            'weight_kg' => $weightKg,

            'is_independent' => $independent,
            'company_name' => $data['company_name'],
            'company_nit' => $independent ? null : ($data['company_nit'] ?? null),
            'client_company' => $data['client_company'] ?? null,
            'economic_activity' => $data['economic_activity'] ?? null,
            'eps_id' => $data['eps_id'] ?? null,
            'arl_id' => $data['arl_id'],
            'afp_id' => $data['afp_id'] ?? null,
            'city_id' => $data['city_id'],
            'position' => $data['position'],

            'exam_date' => $data['exam_date'],
            'exam_type' => $data['exam_type'],

            'medical_parameters' => $this->buildParameters($data, $heightCm, $weightKg),
            'paraclinicals' => $this->buildParaclinicals($data),

            ...$aptitudes,
            'result' => $this->overallResult($aptitudes),

            'clinical_findings' => $data['clinical_findings'] ?? null,
            'recommendations' => implode("\n", $this->parameters->recommendations()),
            'restrictions' => $data['restrictions'] ?? null,
            'restrictions_validity' => $data['restrictions_validity'] ?? null,
            'consent_accepted' => $data['consent_accepted'] ?? true,
        ];
    }

    /**
     * Parte de los valores precargados y encima aplica lo que envio el medico,
     * de modo que un formulario parcial tambien produce un examen completo.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildParameters(array $data, int $heightCm, float $weightKg): array
    {
        $parameters = $this->parameters->generate($heightCm, $weightKg);

        foreach (self::OVERRIDABLE as $block) {
            if (! is_array($data[$block] ?? null)) {
                continue;
            }

            $parameters[$block] = array_replace(
                $parameters[$block],
                array_filter($data[$block], fn ($value) => $value !== null && $value !== ''),
            );
        }

        // La antropometria siempre se recalcula: depende de estatura y peso.
        $parameters['anthropometry']['weight_kg'] = $weightKg;

        return $parameters;
    }

    /** @param array<string, mixed> $data */
    private function buildParaclinicals(array $data): array
    {
        $paraclinicals = $this->parameters->paraclinicals();

        foreach ($data['paraclinicals'] ?? [] as $key => $values) {
            if (isset($paraclinicals[$key]) && is_array($values)) {
                $paraclinicals[$key] = array_replace($paraclinicals[$key], $values);
            }
        }

        return $paraclinicals;
    }

    /**
     * El concepto global del certificado es el mas restrictivo de los tres.
     *
     * @param  array<string, string|null>  $aptitudes
     */
    private function overallResult(array $aptitudes): ExamResult
    {
        return ExamResult::mostRestrictive(array_map(
            fn (?string $value) => $value ? ExamResult::from($value) : null,
            array_values($aptitudes),
        ));
    }

    private function verificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(6).'-'.Str::random(6).'-'.Str::random(6));
        } while (MedicalExam::where('verification_code', $code)->exists());

        return $code;
    }
}
