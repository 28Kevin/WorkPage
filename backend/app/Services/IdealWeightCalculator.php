<?php

namespace App\Services;

/**
 * Calcula el peso adecuado a partir de la estatura usando el Indice de Masa
 * Corporal (IMC = peso / estatura^2). Se toma un IMC objetivo de 22, punto
 * medio del rango saludable definido por la OMS (18.5 - 24.9).
 */
class IdealWeightCalculator
{
    public const TARGET_BMI = 22.0;

    public const MIN_BMI = 18.5;

    public const MAX_BMI = 24.9;

    /** @return array{height_cm: int, ideal_weight_kg: float, min_weight_kg: float, max_weight_kg: float, target_bmi: float} */
    public function forHeight(int $heightCm): array
    {
        $meters = $heightCm / 100;
        $squared = $meters ** 2;

        return [
            'height_cm' => $heightCm,
            'ideal_weight_kg' => round(self::TARGET_BMI * $squared, 1),
            'min_weight_kg' => round(self::MIN_BMI * $squared, 1),
            'max_weight_kg' => round(self::MAX_BMI * $squared, 1),
            'target_bmi' => self::TARGET_BMI,
        ];
    }

    public function bmi(float $weightKg, int $heightCm): float
    {
        return round($weightKg / (($heightCm / 100) ** 2), 1);
    }
}
