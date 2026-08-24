<?php

namespace App\Enums;

enum ExamType: string
{
    case Ingreso = 'ingreso';
    case Periodico = 'periodico';
    case Seguimiento = 'seguimiento';
    case Retorno = 'retorno';
    case CambioOcupacion = 'cambio_ocupacion';

    public function label(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Periodico => 'Periódico',
            self::Seguimiento => 'Seguimiento',
            self::Retorno => 'Retorno',
            self::CambioOcupacion => 'Cambio de ocupación',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
