<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingConcept;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Opciones de la lista desplegable de items, editables desde el panel. */
class BillingConceptController extends Controller
{
    /** Panel: incluye las inactivas, para poder reactivarlas. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => BillingConcept::ordered()->get(['id', 'name', 'position', 'active']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ], [], ['name' => 'concepto']);

        $concept = BillingConcept::create([
            'name' => $validated['name'],
            'position' => (int) BillingConcept::max('position') + 1,
            'active' => true,
        ]);

        return response()->json(['data' => $concept], 201);
    }

    public function update(Request $request, BillingConcept $concept): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'active' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:999'],
        ], [], ['name' => 'concepto']);

        $concept->update($validated);

        return response()->json(['data' => $concept->fresh()]);
    }

    /**
     * No se borra: las cuentas ya emitidas guardan el texto del concepto, pero
     * desactivarlo deja intacto el historial y conserva el orden del panel.
     */
    public function destroy(BillingConcept $concept): JsonResponse
    {
        $concept->update(['active' => false]);

        return response()->json([
            'message' => 'El concepto dejó de aparecer en la lista.',
            'data' => $concept->fresh(),
        ]);
    }
}
