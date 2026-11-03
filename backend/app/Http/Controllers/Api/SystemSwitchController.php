<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SystemSwitch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSwitchController extends Controller
{
    /** Publico: la SPA lo consulta para decidir si muestra la pantalla de aviso. */
    public function show(): JsonResponse
    {
        return response()->json(['enabled' => SystemSwitch::enabled()]);
    }

    public function update(Request $request): JsonResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        SystemSwitch::set((bool) $enabled);

        return response()->json([
            'message' => $enabled ? 'Plataforma encendida.' : 'Plataforma apagada.',
            'enabled' => SystemSwitch::enabled(),
        ]);
    }
}
