<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFoodHandlerRecordRequest;
use App\Http\Requests\UpdateFoodHandlerRecordRequest;
use App\Http\Resources\FoodHandlerRecordListResource;
use App\Http\Resources\FoodHandlerRecordResource;
use App\Models\FoodHandlerRecord;
use App\Services\FoodHandlerPdfGenerator;
use App\Services\FoodHandlerRecordGenerator;
use App\Services\OrderNumberGenerator;
use App\Support\FoodHandlerForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class FoodHandlerRecordController extends Controller
{
    private const RELATIONS = ['creator'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $records = FoodHandlerRecord::query()
            // Los anulados quedan fuera salvo que se pidan expresamente.
            ->when($request->input('status', 'active') === 'active', fn ($q) => $q->active())
            ->when($request->input('status') === 'annulled', fn ($q) => $q->annulled())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search'));
                $query->where(fn ($q) => $q
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('order_code', 'like', "%{$search}%"));
            })
            ->latest('order_number')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return FoodHandlerRecordListResource::collection($records);
    }

    public function store(
        StoreFoodHandlerRecordRequest $request,
        FoodHandlerRecordGenerator $generator,
    ): JsonResponse {
        $record = $generator->create($request->validated(), $request->user());

        return FoodHandlerRecordResource::make($record)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(FoodHandlerRecord $record): FoodHandlerRecordResource
    {
        return FoodHandlerRecordResource::make($record->load(self::RELATIONS));
    }

    /** Corrige el documento conservando consecutivo y codigo de verificacion. */
    public function update(
        UpdateFoodHandlerRecordRequest $request,
        FoodHandlerRecord $record,
        FoodHandlerRecordGenerator $generator,
    ): FoodHandlerRecordResource|JsonResponse {
        if ($record->isAnnulled()) {
            return response()->json([
                'message' => 'El documento está anulado y no puede corregirse.',
            ], Response::HTTP_CONFLICT);
        }

        return FoodHandlerRecordResource::make($generator->update($record, $request->validated()));
    }

    /**
     * Anula en vez de borrar: el QR del carne ya impreso debe seguir
     * respondiendo, ahora indicando que el documento perdio validez.
     */
    public function destroy(Request $request, FoodHandlerRecord $record): JsonResponse
    {
        if ($record->isAnnulled()) {
            return response()->json([
                'message' => 'El documento ya estaba anulado.',
            ], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['reason' => 'motivo de anulación']);

        $record->update([
            'annulled_at' => now(),
            'annulment_reason' => $validated['reason'],
            'annulled_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'El documento fue anulado. El código QR seguirá respondiendo como documento sin validez.',
            'data' => FoodHandlerRecordResource::make($record->load(self::RELATIONS))->resolve(),
        ]);
    }

    /** ?inline=1 para la vista previa; sin el parametro, descarga. */
    public function pdf(FoodHandlerRecord $record, FoodHandlerPdfGenerator $pdfGenerator, Request $request)
    {
        $pdf = $pdfGenerator->make($record);
        $filename = $pdfGenerator->filename($record);

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    public function nextOrderNumber(OrderNumberGenerator $orderNumbers): JsonResponse
    {
        $next = $orderNumbers->current(OrderNumberGenerator::FOOD_HANDLER_KEY) + 1;

        return response()->json([
            'next_order_number' => $next,
            'next_order_code' => $orderNumbers->format(
                $next,
                null,
                OrderNumberGenerator::FOOD_HANDLER_PREFIX,
            ),
        ]);
    }

    /**
     * Borrador del formulario: el catalogo de examenes con su valor normal ya
     * precargado, para confirmar o corregir en vez de escribirlo todo.
     */
    public function draft(): JsonResponse
    {
        return response()->json([
            'lab_catalog' => FoodHandlerForm::catalog(),
            'lab_results' => FoodHandlerForm::normalResults(),
        ]);
    }
}
