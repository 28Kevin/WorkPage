<?php

namespace App\Services;

use App\Support\ExamForm;

/**
 * Precarga el examen con valores dentro de rangos normales. El medico puede
 * corregir cualquiera de ellos desde el formulario antes de emitir.
 */
class MedicalParameterGenerator
{
    public function __construct(private readonly IdealWeightCalculator $weights) {}

    /** @return array<string, mixed> */
    public function generate(int $heightCm, ?float $weightKg = null): array
    {
        $reference = $this->weights->forHeight($heightCm);
        $weightKg ??= $this->randomFloat($reference['ideal_weight_kg'] - 3, $reference['ideal_weight_kg'] + 3, 1);
        $bmi = $this->weights->bmi($weightKg, $heightCm);

        $systolic = random_int(105, 125);
        $diastolic = random_int(68, 82);

        return [
            'vitals' => [
                'systolic' => $systolic,
                'diastolic' => $diastolic,
                'heart_rate' => random_int(62, 84),
                'respiratory_rate' => random_int(14, 18),
                'temperature' => $this->randomFloat(36.3, 37.0, 1),
                'spo2' => random_int(96, 99),
            ],
            'anthropometry' => [
                'height_cm' => $heightCm,
                'weight_kg' => $weightKg,
                'ideal_weight_kg' => $reference['ideal_weight_kg'],
                'healthy_range_kg' => $reference['min_weight_kg'].' - '.$reference['max_weight_kg'],
                'bmi' => $bmi,
                'bmi_classification' => $this->classifyBmi($bmi),
            ],
            'vision' => [
                'right_eye' => $this->visualAcuity(),
                'left_eye' => $this->visualAcuity(),
                'optical_correction' => false,
            ],
            'systems' => array_fill_keys(array_keys(ExamForm::SYSTEMS), ExamForm::NORMAL),
            'assessments' => [
                'visual' => 'Agudeza visual conservada, visión cromática y de profundidad normales.',
                'hearing' => 'Audición dentro de límites normales de forma bilateral.',
                'respiratory' => 'Patrón espirométrico normal, sin signos de obstrucción ni restricción.',
                'cardiovascular' => 'Ritmo cardíaco regular y examen neurológico sin déficit.',
            ],
            'history' => [
                'personal' => 'No refiere',
                'family' => 'No refiere',
                'occupational' => 'No refiere accidentes ni enfermedades laborales',
                'allergic' => 'No refiere',
                'surgical' => 'No refiere',
            ],
        ];
    }

    /**
     * Paraclinicos precargados como realizados y normales.
     *
     * @return array<string, array{performed: bool, status: string, result: string}>
     */
    public function paraclinicals(): array
    {
        $results = [
            'visiometria' => 'Agudeza visual 20/20 en ambos ojos',
            'audiometria' => 'Umbrales auditivos entre 0 y 25 dB',
            'espirometria' => 'CVF y VEF1 por encima del 90 % del predicho',
            'electrocardiograma' => 'Ritmo sinusal normal',
            'glicemia' => random_int(76, 96).' mg/dL',
            'colesterol' => random_int(150, 189).' mg/dL',
            'trigliceridos' => random_int(80, 145).' mg/dL',
        ];

        $paraclinicals = [];

        foreach (ExamForm::PARACLINICALS as $key => $label) {
            $paraclinicals[$key] = [
                'performed' => true,
                'status' => ExamForm::NORMAL,
                'result' => $results[$key] ?? '',
            ];
        }

        return $paraclinicals;
    }

    /** @return array<int, string> */
    public function recommendations(): array
    {
        return [
            'Continuar con hábitos de vida saludable y actividad física regular.',
            'Uso permanente de los elementos de protección personal asignados al cargo.',
            'Participar en los programas de vigilancia epidemiológica de la empresa.',
            'Realizar pausas activas durante la jornada laboral.',
            'Asistir al próximo examen médico ocupacional periódico según programación.',
        ];
    }

    /**
     * Agudeza visual dentro de lo normal. 20/20 es lo esperado y 20/25 sigue
     * siendo normal, asi que se sortea con mas peso en el primero.
     */
    private function visualAcuity(): string
    {
        return random_int(1, 10) <= 7 ? '20/20' : '20/25';
    }

    private function classifyBmi(float $bmi): string
    {
        return match (true) {
            $bmi < 18.5 => 'Bajo peso',
            $bmi < 25.0 => 'Normal',
            $bmi < 30.0 => 'Sobrepeso',
            default => 'Obesidad',
        };
    }

    private function randomFloat(float $min, float $max, int $decimals): float
    {
        $factor = 10 ** $decimals;

        return random_int((int) round($min * $factor), (int) round($max * $factor)) / $factor;
    }
}
