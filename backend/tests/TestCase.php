<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Red de seguridad contra el borrado accidental de datos reales.
     *
     * RefreshDatabase trunca la conexión activa, y esa conexión depende del
     * entorno: dentro de Docker, Compose exporta DB_CONNECTION=pgsql a $_SERVER,
     * que es justo lo que Laravel consulta primero. Si esa configuración llegara
     * a imponerse sobre phpunit.xml, la suite arrasaría la base de desarrollo.
     *
     * Se valida en refreshApplication() —no en setUp()— porque es el último
     * punto anterior a setUpTraits(), donde RefreshDatabase hace el truncado.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            throw new RuntimeException(
                "Las pruebas deben correr sobre sqlite en memoria, pero la conexión activa es [{$connection}]. "
                .'Ejecutarlas así borraría la base de datos: revise las entradas <server> de phpunit.xml.'
            );
        }

        $database = config("database.connections.{$connection}.database");

        if ($database !== ':memory:') {
            throw new RuntimeException(
                "Las pruebas deben usar sqlite en memoria, pero apuntan a [{$database}]. "
                .'Revise las entradas <server> de phpunit.xml antes de continuar.'
            );
        }
    }
}
