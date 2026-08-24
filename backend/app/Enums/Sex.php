<?php

namespace App\Enums;

enum Sex: string
{
    case Femenino = 'F';
    case Masculino = 'M';
    case Otro = 'O';

    public function label(): string
    {
        return match ($this) {
            self::Femenino => 'Femenino',
            self::Masculino => 'Masculino',
            self::Otro => 'Otro',
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
