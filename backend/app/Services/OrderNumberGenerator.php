<?php

namespace App\Services;

use App\Models\ExamSequence;
use Illuminate\Support\Facades\DB;

/**
 * Numeracion consecutiva: genera de forma automatica y ascendente el numero de
 * orden por cada documento emitido. El bloqueo pesimista evita duplicados
 * cuando dos usuarios generan un examen al mismo tiempo.
 */
class OrderNumberGenerator
{
    /** Secuencia del examen medico ocupacional. */
    public const KEY = 'medical_exam_order';

    public const PREFIX = 'EMO';

    /**
     * Secuencia del paquete de manipulacion de alimentos. Cada tipo de
     * documento lleva su propio contador y su propio prefijo: el serial dice
     * de que documento se trata sin tener que consultarlo.
     */
    public const FOOD_HANDLER_KEY = 'food_handler_order';

    public const FOOD_HANDLER_PREFIX = 'MA';

    /**
     * Secuencia del certificado de capacitacion. Su formato no lleva anio ni
     * relleno: el documento de guia numera "S.S-1370".
     */
    public const TRAINING_KEY = 'training_certificate_order';

    public const TRAINING_PREFIX = 'S.S';

    /** Secuencia de las cuentas de cobro: consecutivo de cinco digitos. */
    public const BILLING_KEY = 'billing_account_number';

    public function next(string $key = self::KEY): int
    {
        return DB::transaction(function () use ($key) {
            $sequence = ExamSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(['key' => $key], ['current_value' => 0]);

            $sequence->increment('current_value');

            return (int) $sequence->refresh()->current_value;
        });
    }

    public function format(int $number, ?int $year = null, string $prefix = self::PREFIX): string
    {
        $year ??= (int) date('Y');

        return sprintf('%s-%d-%06d', $prefix, $year, $number);
    }

    public function current(string $key = self::KEY): int
    {
        return (int) (ExamSequence::query()->where('key', $key)->value('current_value') ?? 0);
    }
}
