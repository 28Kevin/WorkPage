<?php

namespace App\Services;

use App\Enums\ExamResult;

/**
 * Diligencia automaticamente los campos medicos restantes con valores dentro de
 * rangos normales estandar, garantizando un resultado final de estado APTO.
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
            'result' => ExamResult::Apto->value,
            'signos_vitales' => [
                'presion_arterial' => "{$systolic}/{$diastolic} mmHg",
                'presion_sistolica' => $systolic,
                'presion_diastolica' => $diastolic,
                'frecuencia_cardiaca' => random_int(62, 84).' lpm',
                'frecuencia_respiratoria' => random_int(14, 18).' rpm',
                'temperatura' => $this->randomFloat(36.3, 37.0, 1).' °C',
                'saturacion_oxigeno' => random_int(96, 99).' %',
            ],
            'antropometria' => [
                'estatura_cm' => $heightCm,
                'peso_kg' => $weightKg,
                'peso_ideal_kg' => $reference['ideal_weight_kg'],
                'rango_peso_saludable_kg' => $reference['min_weight_kg'].' - '.$reference['max_weight_kg'],
                'imc' => $bmi,
                'clasificacion_imc' => 'Normal',
            ],
            'agudeza_visual' => [
                'ojo_derecho' => '20/20',
                'ojo_izquierdo' => '20/20',
                'vision_binocular' => '20/20',
                'vision_cromatica' => 'Normal (Ishihara sin alteraciones)',
                'vision_profundidad' => 'Conservada',
            ],
            'audiometria' => [
                'oido_derecho' => 'Dentro de límites normales (0-25 dB)',
                'oido_izquierdo' => 'Dentro de límites normales (0-25 dB)',
                'concepto' => 'Audición normal bilateral',
            ],
            'espirometria' => [
                'cvf' => random_int(92, 104).' % del predicho',
                'vef1' => random_int(90, 103).' % del predicho',
                'relacion_vef1_cvf' => random_int(80, 88).' %',
                'concepto' => 'Patrón espirométrico normal',
            ],
            'laboratorio' => [
                'glicemia' => random_int(76, 96).' mg/dL',
                'colesterol_total' => random_int(150, 189).' mg/dL',
                'trigliceridos' => random_int(80, 145).' mg/dL',
                'hemoglobina' => $this->randomFloat(13.5, 16.5, 1).' g/dL',
                'concepto' => 'Resultados dentro de parámetros normales',
            ],
            'examen_fisico' => [
                'cabeza_cuello' => 'Normal',
                'cardiopulmonar' => 'Ruidos cardíacos rítmicos, murmullo vesicular conservado',
                'abdomen' => 'Blando, depresible, no doloroso',
                'osteomuscular' => 'Sin limitación funcional, arcos de movilidad completos',
                'neurologico' => 'Consciente, orientado, sin déficit motor ni sensitivo',
                'piel_faneras' => 'Sin lesiones',
                'columna' => 'Sin desviaciones ni dolor a la palpación',
            ],
            'antecedentes' => [
                'personales' => 'No refiere',
                'familiares' => 'No refiere',
                'ocupacionales' => 'No refiere accidentes ni enfermedades laborales',
                'alergicos' => 'No refiere',
                'quirurgicos' => 'No refiere',
            ],
            'concepto_medico' => [
                'diagnostico' => 'Z00.0 - Examen médico general sin hallazgos patológicos',
                'restricciones' => 'Ninguna',
                'observaciones' => 'Trabajador sin hallazgos que contraindiquen el desempeño del cargo.',
            ],
        ];
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

    private function randomFloat(float $min, float $max, int $decimals): float
    {
        $factor = 10 ** $decimals;

        return random_int((int) round($min * $factor), (int) round($max * $factor)) / $factor;
    }
}
