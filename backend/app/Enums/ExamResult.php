<?php

namespace App\Enums;

/** Conceptos de aptitud del certificado, segun el formato ocupacional. */
enum ExamResult: string
{
    case Apto = 'APTO';
    case AptoConRestricciones = 'APTO_CON_RESTRICCIONES';
    case Aplazado = 'APLAZADO';
    case NoApto = 'NO_APTO';

    public function label(): string
    {
        return match ($this) {
            self::Apto => 'APTO',
            self::AptoConRestricciones => 'APTO CON RECOMENDACIONES / RESTRICCIONES',
            self::Aplazado => 'APLAZADO',
            self::NoApto => 'NO APTO',
        };
    }

    /** Etiqueta corta para tablas y chips. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Apto => 'Apto',
            self::AptoConRestricciones => 'Apto con restricciones',
            self::Aplazado => 'Aplazado',
            self::NoApto => 'No apto',
        };
    }

    /** Orden de severidad: el concepto global es el mas restrictivo de los tres. */
    public function severity(): int
    {
        return match ($this) {
            self::Apto => 0,
            self::AptoConRestricciones => 1,
            self::Aplazado => 2,
            self::NoApto => 3,
        };
    }

    /** @param array<int, self|null> $concepts */
    public static function mostRestrictive(array $concepts): self
    {
        $filtered = array_filter($concepts);

        if ($filtered === []) {
            return self::Apto;
        }

        usort($filtered, fn (self $a, self $b) => $b->severity() <=> $a->severity());

        return $filtered[0];
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
