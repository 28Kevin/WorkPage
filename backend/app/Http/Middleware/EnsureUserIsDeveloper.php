<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restringe el tablero de uso a las cuentas de desarrollo. */
class EnsureUserIsDeveloper
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isDeveloper()) {
            return response()->json([
                'message' => 'Esta sección está reservada para cuentas de desarrollo.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
