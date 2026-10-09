<?php

namespace App\Support;

use NumberFormatter;

/**
 * Importes de la cuenta de cobro, en numeros y en letras.
 *
 * El documento de guia escribe "$3’160.000" y "Tres millones ciento sesenta
 * mil pesos M/CTE": miles con punto y el apostrofo tipografico marcando los
 * millones, como se acostumbra en Colombia.
 */
class MoneyInWords
{
    /** Separador de millones del formato colombiano. */
    private const MILLIONS = '’';

    private const THOUSANDS = '.';

    /** "$3’160.000" */
    public static function format(int $amount): string
    {
        return '$'.self::groups($amount);
    }

    /** "3’160.000", sin el signo. */
    public static function groups(int $amount): string
    {
        $digits = (string) abs($amount);

        // Grupos de tres, de derecha a izquierda.
        $groups = array_reverse(array_map(
            'strrev',
            str_split(strrev($digits), 3),
        ));

        $out = $groups[0];
        $count = count($groups);

        for ($i = 1; $i < $count; $i++) {
            // El apostrofo va en el separador que deja dos grupos a su derecha.
            $out .= ($i === $count - 2 ? self::MILLIONS : self::THOUSANDS).$groups[$i];
        }

        return ($amount < 0 ? '-' : '').$out;
    }

    /** "Tres millones ciento sesenta mil pesos M/CTE" */
    public static function spell(int $amount): string
    {
        $words = self::words($amount);

        // "dos millones de pesos", pero "tres millones ciento sesenta mil
        // pesos": el "de" solo aparece cuando la cifra termina en millon.
        $needsDe = preg_match('~(mill|bill)(ón|ones)$~u', $words) === 1;

        return self::upperFirst($words).($needsDe ? ' de' : '').' pesos M/CTE';
    }

    /** Solo las palabras del numero, en minuscula. */
    public static function words(int $amount): string
    {
        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $words = $formatter->format($amount);

        if ($words === false) {
            return (string) $amount;
        }

        // El formateador escribe "uno" suelto; delante de "peso" va "un".
        return trim($words);
    }

    /**
     * Mayuscula inicial respetando los acentos: ucfirst() trabaja byte a byte
     * y rompe una palabra que empiece por un caracter multibyte.
     */
    private static function upperFirst(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
