<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Ordenamiento y tamanio de pagina comunes a los listados del panel. */
trait SortsListings
{
    /** Tamanios de pagina que ofrece el panel. */
    public const PAGE_SIZES = [10, 15, 25, 50, 100];

    public const DEFAULT_PAGE_SIZE = 15;

    /**
     * Aplica el orden pedido por el navegador.
     *
     * @param  array<string, string>  $sortable  campo del listado => columna real
     * @param  string  $default  campo que se usa si no piden otro o piden uno que no existe
     */
    protected function sorted(Builder $query, Request $request, array $sortable, string $default): Builder
    {
        $field = (string) $request->string('sort');

        // Lista blanca: lo que llega del navegador nunca entra directo en un
        // ORDER BY, ni siquiera para fallar con un nombre de columna invalido.
        $column = $sortable[$field] ?? $sortable[$default];

        $direction = strtolower((string) $request->string('direction')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($column, $direction);

        /*
         * Desempate estable. Al ordenar por una columna con valores repetidos
         * (una fecha, un tipo de examen), el motor no garantiza en que orden
         * salen las filas empatadas, y entre una pagina y la siguiente puede
         * repetir unas y saltarse otras.
         */
        if ($column !== 'id') {
            $query->orderBy('id', $direction);
        }

        return $query;
    }

    /** Filas por pagina, limitadas a las opciones que ofrece el panel. */
    protected function perPage(Request $request): int
    {
        $size = $request->integer('per_page', self::DEFAULT_PAGE_SIZE);

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }
}
