<?php

namespace App\Enums;

enum ExamResult: string
{
    case Apto = 'APTO';
    case AptoConRestricciones = 'APTO_CON_RESTRICCIONES';
    case NoApto = 'NO_APTO';

    public function label(): string
    {
        return match ($this) {
            self::Apto => 'APTO',
            self::AptoConRestricciones => 'APTO CON RESTRICCIONES',
            self::NoApto => 'NO APTO',
        };
    }
}
