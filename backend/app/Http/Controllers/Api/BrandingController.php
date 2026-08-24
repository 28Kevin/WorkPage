<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandingRequest;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;

class BrandingController extends Controller
{
    /** Publico: la SPA lo consulta antes de pintar, incluso sin sesion. */
    public function show(): JsonResponse
    {
        return response()->json(['branding' => Branding::all()]);
    }

    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        Setting::putMany(Branding::flatten($request->validated()));

        return response()->json([
            'message' => 'Configuración actualizada.',
            'branding' => Branding::all(),
        ]);
    }
}
