<?php

namespace App\Http\Middleware;

use App\Support\SystemSwitch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Con la plataforma apagada responde 503 a todo salvo a lo imprescindible para volver a encenderla. */
class EnsureSystemIsEnabled
{
    /** Lo que la SPA necesita para pintar la pantalla de aviso y para que un desarrollador ingrese. */
    private const ALWAYS_OPEN = [
        'api.system.show',
        'api.branding.show',
        'api.auth.login',
        'api.auth.me',
        'api.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (SystemSwitch::enabled() || $request->routeIs(...self::ALWAYS_OPEN)) {
            return $next($request);
        }

        // Corre antes de auth:sanctum, por eso se resuelve el guard aqui.
        if ($request->user('sanctum')?->isDeveloper()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Servicio no disponible. Comuníquese con el desarrollador.',
            'code' => 'system_disabled',
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
