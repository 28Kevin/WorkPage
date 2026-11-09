<?php

namespace App\Enums;

/** Grupo sanguineo y factor RH del trabajador. */
enum BloodType: string
{
    case OPositivo = 'O+';
    case ONegativo = 'O-';
    case APositivo = 'A+';
    case ANegativo = 'A-';
    case BPositivo = 'B+';
    case BNegativo = 'B-';
    case ABPositivo = 'AB+';
    case ABNegativo = 'AB-';

    public function label(): string
    {
        return $this->value;
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
