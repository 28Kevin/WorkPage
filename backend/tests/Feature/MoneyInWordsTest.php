<?php

namespace Tests\Feature;

use App\Support\MoneyInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El importe en letras de la cuenta de cobro.
 *
 * Las esperas van escritas a mano, sin comparar contra intl: la imagen de
 * produccion es Alpine y su ICU trae los datos recortados, asi que una prueba
 * apoyada en intl pasaria en local y no diria nada del servidor.
 */
class MoneyInWordsTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function amounts(): array
    {
        return [
            'cero' => [0, 'Cero pesos M/CTE'],
            // Singular: "un peso", no "un pesos".
            'uno' => [1, 'Un peso M/CTE'],
            'quince' => [15, 'Quince pesos M/CTE'],
            // Apocope delante del sustantivo masculino.
            'veintiuno' => [21, 'Veintiún pesos M/CTE'],
            'treinta y uno' => [31, 'Treinta y un pesos M/CTE'],
            'cien exacto' => [100, 'Cien pesos M/CTE'],
            'ciento uno' => [101, 'Ciento un pesos M/CTE'],
            'doscientos' => [200, 'Doscientos pesos M/CTE'],
            'quinientos' => [500, 'Quinientos pesos M/CTE'],
            // Nunca "un mil".
            'mil' => [1000, 'Mil pesos M/CTE'],
            'mil quinientos' => [1500, 'Mil quinientos pesos M/CTE'],
            'veintiun mil' => [21000, 'Veintiún mil pesos M/CTE'],
            'cincuenta mil' => [50000, 'Cincuenta mil pesos M/CTE'],
            'ciento cincuenta mil' => [150000, 'Ciento cincuenta mil pesos M/CTE'],
            // El total del segundo documento de guia.
            'quinientos treinta mil' => [530000, 'Quinientos treinta mil pesos M/CTE'],
            // "de pesos" cuando la cifra termina en millon.
            'un millon' => [1000000, 'Un millón de pesos M/CTE'],
            'dos millones' => [2000000, 'Dos millones de pesos M/CTE'],
            'millon con resto' => [1500000, 'Un millón quinientos mil pesos M/CTE'],
            // El total del primer documento de guia.
            'tres millones' => [3160000, 'Tres millones ciento sesenta mil pesos M/CTE'],
            'cifra larga' => [
                1234567890,
                'Mil doscientos treinta y cuatro millones quinientos sesenta y siete mil ochocientos noventa pesos M/CTE',
            ],
        ];
    }

    #[DataProvider('amounts')]
    public function test_it_spells_the_amount_in_spanish(int $amount, string $expected): void
    {
        $this->assertSame($expected, MoneyInWords::spell($amount));
    }

    public function test_it_never_falls_back_to_english(): void
    {
        /*
         * Es el fallo que se vio en produccion: con los datos de ICU
         * recortados, el importe salia como "three million one hundred sixty
         * thousand". Ahora las letras se arman en PHP y no dependen de ICU.
         */
        // Palabras completas: "one" suelto es subcadena de "millones".
        $english = ['thousand', 'million', 'millions', 'hundred', 'one', 'two', 'three', 'and', 'zero'];

        foreach ([0, 1, 101, 3160000, 530000, 1234567890] as $amount) {
            $words = strtolower(MoneyInWords::spell($amount));

            foreach ($english as $word) {
                $this->assertDoesNotMatchRegularExpression(
                    '~\b'.$word.'\b~u',
                    $words,
                    "El importe {$amount} salió con la palabra inglesa «{$word}»: {$words}",
                );
            }
        }
    }

    public function test_it_does_not_hide_soft_hyphens_in_the_words(): void
    {
        // intl insertaba U+00AD dentro de "nove­cientos": invisible en pantalla,
        // pero viaja en el PDF y rompe cualquier busqueda del texto.
        $this->assertStringNotContainsString("\u{00AD}", MoneyInWords::spell(900000));
        $this->assertSame('Novecientos mil pesos M/CTE', MoneyInWords::spell(900000));
    }

    public function test_it_writes_the_number_the_colombian_way(): void
    {
        // Apostrofo para los millones y punto para los miles.
        $this->assertSame('$3’160.000', MoneyInWords::format(3160000));
        $this->assertSame('$530.000', MoneyInWords::format(530000));
        $this->assertSame('$50.000', MoneyInWords::format(50000));
        $this->assertSame('$999', MoneyInWords::format(999));
        $this->assertSame('$1.234’567.890', MoneyInWords::format(1234567890));
    }
}
