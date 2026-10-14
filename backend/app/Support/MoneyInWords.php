<?php

namespace App\Support;

/**
 * Importes de la cuenta de cobro, en numeros y en letras.
 *
 * El documento de guia escribe "$3’160.000" y "Tres millones ciento sesenta
 * mil pesos M/CTE": miles con punto y el apostrofo tipografico marcando los
 * millones, como se acostumbra en Colombia.
 *
 * Las letras se arman aqui y no con NumberFormatter de intl. La imagen de
 * produccion es Alpine, cuyo paquete de ICU trae los datos recortados: las
 * reglas de numeros en letras solo existen para el locale raiz, que es ingles,
 * asi que el importe salia en ingles en el servidor y en espaniol en local.
 */
class MoneyInWords
{
    /** Separador de millones del formato colombiano. */
    private const MILLIONS = '’';

    private const THOUSANDS = '.';

    /** Hasta 29 se nombran de corrido; de ahi en adelante se componen. */
    private const UNITS = [
        0 => 'cero', 1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro',
        5 => 'cinco', 6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve',
        10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce',
        15 => 'quince', 16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho',
        19 => 'diecinueve', 20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós',
        23 => 'veintitrés', 24 => 'veinticuatro', 25 => 'veinticinco',
        26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho',
        29 => 'veintinueve',
    ];

    private const TENS = [
        3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta',
        7 => 'setenta', 8 => 'ochenta', 9 => 'noventa',
    ];

    private const HUNDREDS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos',
        5 => 'quinientos', 6 => 'seiscientos', 7 => 'setecientos',
        8 => 'ochocientos', 9 => 'novecientos',
    ];

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

        $noun = abs($amount) === 1 ? 'peso' : 'pesos';

        return self::upperFirst($words).($needsDe ? ' de' : '').' '.$noun.' M/CTE';
    }

    /**
     * Las palabras del numero, en minuscula y en la forma que corresponde
     * delante de "pesos": por eso 101 es "ciento un" y no "ciento uno".
     */
    public static function words(int $amount): string
    {
        if ($amount === 0) {
            return self::UNITS[0];
        }

        $sign = $amount < 0 ? 'menos ' : '';
        $amount = abs($amount);

        $millions = intdiv($amount, 1_000_000);
        $rest = $amount % 1_000_000;

        $parts = [];

        if ($millions === 1) {
            $parts[] = 'un millón';
        } elseif ($millions > 1) {
            $parts[] = self::belowMillion($millions).' millones';
        }

        if ($rest > 0) {
            $parts[] = self::belowMillion($rest);
        }

        return $sign.implode(' ', $parts);
    }

    /** Numeros de 1 a 999.999. */
    private static function belowMillion(int $amount): string
    {
        $thousands = intdiv($amount, 1000);
        $units = $amount % 1000;

        $parts = [];

        if ($thousands === 1) {
            // "mil quinientos", nunca "un mil quinientos".
            $parts[] = 'mil';
        } elseif ($thousands > 1) {
            $parts[] = self::belowThousand($thousands).' mil';
        }

        if ($units > 0) {
            $parts[] = self::belowThousand($units);
        }

        return implode(' ', $parts);
    }

    /**
     * Numeros de 1 a 999, siempre en la forma apocopada: lo que sigue es
     * "mil", "millones" o "pesos", y delante de un sustantivo masculino se
     * dice "un", "veintiún", "treinta y un".
     */
    private static function belowThousand(int $amount): string
    {
        if ($amount === 100) {
            return 'cien';
        }

        $hundreds = intdiv($amount, 100);
        $rest = $amount % 100;

        $parts = [];

        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        if ($rest > 0) {
            $parts[] = self::belowHundred($rest);
        }

        return implode(' ', $parts);
    }

    /** Numeros de 1 a 99, apocopados. */
    private static function belowHundred(int $amount): string
    {
        if ($amount < 30) {
            return match ($amount) {
                1 => 'un',
                21 => 'veintiún',
                default => self::UNITS[$amount],
            };
        }

        $tens = intdiv($amount, 10);
        $units = $amount % 10;

        if ($units === 0) {
            return self::TENS[$tens];
        }

        return self::TENS[$tens].' y '.($units === 1 ? 'un' : self::UNITS[$units]);
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
