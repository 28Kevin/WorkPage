<?php

namespace App\Services;

use App\Enums\ExamResult;
use App\Models\MedicalExam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orquesta la creacion de un examen: numeracion consecutiva, calculo de peso
 * adecuado, diligenciamiento automatico de parametros medicos y codigo de
 * verificacion publica.
 */
class ExamGenerator
{
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
            $examDate = $data['exam_date'];
            $heightCm = (int) $data['height_cm'];

            $reference = $this->weights->forHeight($heightCm);
            $weightKg = isset($data['weight_kg'])
                ? (float) $data['weight_kg']
                : $reference['ideal_weight_kg'];

            $parameters = $this->parameters->generate($heightCm, $weightKg);

            $exam = MedicalExam::create([
                'order_number' => $orderNumber,
                'order_code' => $this->orderNumbers->format($orderNumber, (int) date('Y', strtotime($examDate))),
                'full_name' => $data['full_name'],
                'document_number' => $data['document_number'],
                'birth_date' => $data['birth_date'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'height_cm' => $heightCm,
                'ideal_weight_kg' => $reference['ideal_weight_kg'],
                'weight_kg' => $weightKg,
                'company_name' => $data['company_name'],
                'company_nit' => $data['company_nit'],
                'eps_id' => $data['eps_id'],
                'arl_id' => $data['arl_id'],
                'city_id' => $data['city_id'],
                'position' => $data['position'],
                'exam_date' => $examDate,
                'exam_type' => $data['exam_type'],
                'medical_parameters' => $parameters,
                'result' => ExamResult::Apto,
                'recommendations' => implode("\n", $this->parameters->recommendations()),
                'verification_code' => $this->verificationCode(),
                'issued_at' => now(),
                'created_by' => $user?->id,
            ]);

            $exam->risks()->sync($data['risk_ids'] ?? []);

            return $exam->load(['eps', 'arl', 'city', 'risks', 'creator']);
        });
    }

    private function verificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(6).'-'.Str::random(6).'-'.Str::random(6));
        } while (MedicalExam::where('verification_code', $code)->exists());

        return $code;
    }
}
