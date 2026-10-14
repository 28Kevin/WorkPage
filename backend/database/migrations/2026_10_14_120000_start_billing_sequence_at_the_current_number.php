<?php

use App\Models\ExamSequence;
use App\Services\OrderNumberGenerator;
use Illuminate\Database\Migrations\Migration;

/**
 * Las cuentas de cobro ya se venian numerando a mano: la ultima emitida fue la
 * 06012, asi que el contador arranca ahi para que la siguiente sea la 06013 y
 * no se repita ningun numero.
 */
return new class extends Migration
{
    private const LAST_ISSUED = 6012;

    public function up(): void
    {
        $sequence = ExamSequence::firstOrCreate(
            ['key' => OrderNumberGenerator::BILLING_KEY],
            ['current_value' => 0],
        );

        // Solo adelanta: si ya se emitieron cuentas en el sistema, no retrocede.
        if ((int) $sequence->current_value < self::LAST_ISSUED) {
            $sequence->update(['current_value' => self::LAST_ISSUED]);
        }
    }

    public function down(): void
    {
        // No se revierte: devolver el contador reabriria numeros ya usados.
    }
};
