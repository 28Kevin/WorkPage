<?php

namespace App\Http\Middleware;

use App\Support\UsageTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuenta la peticion en el tablero de uso cuando termina bien. El sujeto es
 * el parametro de ruta indicado (p. ej. el codigo verificado) o, si no hay,
 * el nombre del tipo de documento.
 *
 * Uso en rutas: ->middleware('track:public.verify,code')
 */
class TrackUsage
{
    public function handle(Request $request, Closure $next, string $metric, ?string $subject = null): Response
    {
        $response = $next($request);

        if ($response->isSuccessful()) {
            $value = $subject ? $request->route($subject) : '';

            // Los modelos enlazados en la ruta cuentan por tipo, no por registro.
            if (is_object($value)) {
                $value = class_basename($value);
            }

            UsageTracker::record($metric, (string) $value);
        }

        return $response;
    }
}
