<?php

namespace App\Services;

use App\Models\FoodHandlerRecord;
use App\Models\MedicalExam;
use Illuminate\Support\Str;

/**
 * Codigo que viaja en el QR impreso. Una sola pagina publica resuelve los dos
 * tipos de documento, asi que el codigo tiene que ser unico entre ambas tablas:
 * si se repitiera, el QR de un documento abriria el otro.
 */
class VerificationCodeGenerator
{
    public function generate(): string
    {
        do {
            $code = strtoupper(Str::random(6).'-'.Str::random(6).'-'.Str::random(6));
        } while ($this->taken($code));

        return $code;
    }

    private function taken(string $code): bool
    {
        return MedicalExam::where('verification_code', $code)->exists()
            || FoodHandlerRecord::where('verification_code', $code)->exists();
    }
}
