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
    public const KEY = 'medical_exam_order';

    public const PREFIX = 'EMO';

    public function next(): int
    {
        return DB::transaction(function () {
            $sequence = ExamSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(['key' => self::KEY], ['current_value' => 0]);

            $sequence->increment('current_value');

            return (int) $sequence->refresh()->current_value;
        });
    }

    public function format(int $number, ?int $year = null): string
    {
        $year ??= (int) date('Y');

        return sprintf('%s-%d-%06d', self::PREFIX, $year, $number);
    }

    public function current(): int
    {
        return (int) (ExamSequence::query()->where('key', self::KEY)->value('current_value') ?? 0);
    }
}
