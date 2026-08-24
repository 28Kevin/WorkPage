<?php

namespace App\Support;

/**
 * Catalogo de los campos semiestructurados del formato ocupacional. Vive aqui
 * para que el formulario, la validacion y el PDF usen exactamente las mismas
 * claves y etiquetas.
 */
class ExamForm
{
    public const NORMAL = 'normal';

    public const FINDINGS = 'hallazgos';

    public const ALTERED = 'alterada';

    /**
     * Sistemas revisados en el examen fisico. Cada uno se marca normal o con
     * hallazgos; las etiquetas cambian porque el formato no dice "normal" en
     * todos los casos.
     */
    public const SYSTEMS = [
        'balance' => ['label' => 'Equilibrio / coordinación', 'normal' => 'Conservado', 'findings' => 'Alterado'],
        'gait' => ['label' => 'Marcha', 'normal' => 'Normal', 'findings' => 'Alterada'],
        'cardiovascular' => ['label' => 'Cardiovascular', 'normal' => 'Normal', 'findings' => 'Hallazgos'],
        'respiratory' => ['label' => 'Respiratorio', 'normal' => 'Normal', 'findings' => 'Hallazgos'],
        'neurological' => ['label' => 'Neurológico', 'normal' => 'Normal', 'findings' => 'Hallazgos'],
        'musculoskeletal' => ['label' => 'Osteomuscular', 'normal' => 'Normal', 'findings' => 'Hallazgos'],
        'hearing' => ['label' => 'Audición', 'normal' => 'Conservada', 'findings' => 'Alterada'],
        'skin' => ['label' => 'Piel / otros', 'normal' => 'Normal', 'findings' => 'Hallazgos'],
    ];

    /** Pruebas complementarias: realizada o no, concepto y resultado escrito. */
    public const PARACLINICALS = [
        'visiometria' => 'Visiometría / Optometría',
        'audiometria' => 'Audiometría',
        'espirometria' => 'Espirometría',
        'electrocardiograma' => 'Electrocardiograma',
        'glicemia' => 'Glicemia',
        'colesterol' => 'Colesterol',
        'trigliceridos' => 'Triglicéridos',
    ];

    /** Conclusiones por aparato que el formato imprime al final. */
    public const ASSESSMENTS = [
        'visual' => 'Resultado de valoración visual',
        'hearing' => 'Resultado de valoración auditiva',
        'respiratory' => 'Resultado respiratorio / pulmonar',
        'cardiovascular' => 'Resultado cardiovascular / neurológico',
    ];

    /** Los tres conceptos de aptitud del formato. */
    public const APTITUDES = [
        'aptitude_position' => 'Cargo / ocupación',
        'aptitude_heights' => 'Trabajo en alturas',
        'aptitude_confined' => 'Espacios confinados',
    ];

    /** Etiqueta legible del estado de un sistema. */
    public static function systemLabel(string $key, ?string $status): string
    {
        $system = self::SYSTEMS[$key] ?? null;

        if (! $system) {
            return (string) $status;
        }

        return $status === self::FINDINGS ? $system['findings'] : $system['normal'];
    }

    /** Estructura que consume el frontend para pintar el formulario. */
    public static function definitions(): array
    {
        return [
            'systems' => array_map(
                fn (string $key, array $system) => [
                    'key' => $key,
                    'label' => $system['label'],
                    'normal_label' => $system['normal'],
                    'findings_label' => $system['findings'],
                ],
                array_keys(self::SYSTEMS),
                self::SYSTEMS,
            ),
            'paraclinicals' => array_map(
                fn (string $key, string $label) => ['key' => $key, 'label' => $label],
                array_keys(self::PARACLINICALS),
                self::PARACLINICALS,
            ),
            'assessments' => array_map(
                fn (string $key, string $label) => ['key' => $key, 'label' => $label],
                array_keys(self::ASSESSMENTS),
                self::ASSESSMENTS,
            ),
            'aptitudes' => array_map(
                fn (string $key, string $label) => ['key' => $key, 'label' => $label],
                array_keys(self::APTITUDES),
                self::APTITUDES,
            ),
        ];
    }
}
