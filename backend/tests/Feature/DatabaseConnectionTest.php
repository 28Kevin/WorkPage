<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    /**
     * Produccion corre sobre el pooler de Neon, que reparte una conexion del
     * servidor entre peticiones. Con sentencias preparadas del servidor, una
     * migracion que agrega una columna deja planes cacheados que ya no
     * corresponden y la conexion empieza a responder "cached plan must not
     * change result type".
     *
     * Si esta opcion desaparece (por ejemplo al regenerar config/database.php
     * en una actualizacion de Laravel), el despliegue vuelve a romperse, y el
     * sintoma aparece lejos del cambio que lo causo.
     */
    public function test_postgres_emulates_prepared_statements_for_the_connection_pooler(): void
    {
        $options = config('database.connections.pgsql.options');

        $this->assertIsArray($options);
        $this->assertArrayHasKey(PDO::ATTR_EMULATE_PREPARES, $options);
        $this->assertTrue($options[PDO::ATTR_EMULATE_PREPARES]);
    }
}
