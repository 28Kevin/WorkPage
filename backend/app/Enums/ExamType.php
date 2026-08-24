<?php

namespace App\Enums;

enum ExamType: string
{
    case Ingreso = 'ingreso';
    case Periodico = 'periodico';
    case Seguimiento = 'seguimiento';
    case Retorno = 'retorno';
    case CambioOcupacion = 'cambio_ocupacion';
    case Otra = 'otra';

    public function label(): string
    {
        return match ($this) {
            self::Ingreso => 'Preingreso',
            self::Periodico => 'Periódica',
            self::Seguimiento => 'Seguimiento / control',
            self::Retorno => 'Retorno / post-incapacidad',
            self::CambioOcupacion => 'Cambio de ocupación',
            self::Otra => 'Otra',
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
