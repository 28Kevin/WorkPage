<?php

namespace App\Enums;

enum DocumentType: string
{
    case CedulaCiudadania = 'CC';
    case CedulaExtranjeria = 'CE';
    case TarjetaIdentidad = 'TI';
    case Pasaporte = 'PA';
    case PermisoEspecial = 'PEP';

    public function label(): string
    {
        return match ($this) {
            self::CedulaCiudadania => 'Cédula de ciudadanía',
            self::CedulaExtranjeria => 'Cédula de extranjería',
            self::TarjetaIdentidad => 'Tarjeta de identidad',
            self::Pasaporte => 'Pasaporte',
            self::PermisoEspecial => 'Permiso especial de permanencia',
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
