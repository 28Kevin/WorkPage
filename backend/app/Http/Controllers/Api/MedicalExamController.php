<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalExamRequest;
use App\Http\Requests\UpdateMedicalExamRequest;
use App\Http\Resources\MedicalExamListResource;
use App\Http\Resources\MedicalExamResource;
use App\Models\MedicalExam;
use App\Services\ExamGenerator;
use App\Services\ExamPdfGenerator;
use App\Services\IdealWeightCalculator;
use App\Services\MedicalParameterGenerator;
use App\Services\OrderNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MedicalExamController extends Controller
{
    private const RELATIONS = ['eps', 'arl', 'afp', 'city', 'risks', 'creator'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $exams = MedicalExam::query()
            ->with('city')
            // Los anulados quedan fuera salvo que se pidan expresamente.
            ->when($request->input('status', 'active') === 'active', fn ($q) => $q->active())
            ->when($request->input('status') === 'annulled', fn ($q) => $q->annulled())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search'));
                $query->where(fn ($q) => $q
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('order_code', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%"));
            })
            ->when($request->filled('exam_type'), fn ($q) => $q->where('exam_type', $request->input('exam_type')))
            ->latest('order_number')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return MedicalExamListResource::collection($exams);
    }

    public function store(
        StoreMedicalExamRequest $request,
        ExamGenerator $generator,
        IdealWeightCalculator $weights,
    ): JsonResponse {
        $exam = $generator->create($request->validatedWithDefaults($weights), $request->user());

        return MedicalExamResource::make($exam)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(MedicalExam $exam): MedicalExamResource
    {
        return MedicalExamResource::make($exam->load(self::RELATIONS));
    }

    /** Corrige un examen conservando su consecutivo y su codigo de verificacion. */
    public function update(
        UpdateMedicalExamRequest $request,
        MedicalExam $exam,
        ExamGenerator $generator,
        IdealWeightCalculator $weights,
    ): MedicalExamResource|JsonResponse {
        if ($exam->isAnnulled()) {
            return response()->json([
                'message' => 'El examen está anulado y no puede corregirse.',
            ], Response::HTTP_CONFLICT);
        }

        return MedicalExamResource::make(
            $generator->update($exam, $request->validatedWithDefaults($weights)),
        );
    }

    /**
     * Anula el examen en vez de borrarlo: el QR ya impreso debe seguir
     * respondiendo, ahora indicando que el documento perdio validez.
     */
    public function destroy(Request $request, MedicalExam $exam): JsonResponse
    {
        if ($exam->isAnnulled()) {
            return response()->json([
                'message' => 'El examen ya estaba anulado.',
            ], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['reason' => 'motivo de anulación']);

        $exam->update([
            'annulled_at' => now(),
            'annulment_reason' => $validated['reason'],
            'annulled_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'El examen fue anulado. El código QR seguirá respondiendo como documento sin validez.',
            'data' => MedicalExamResource::make($exam->load(self::RELATIONS))->resolve(),
        ]);
    }

    public function pdf(MedicalExam $exam, ExamPdfGenerator $pdfGenerator, Request $request)
    {
        $pdf = $pdfGenerator->make($exam);
        $filename = $pdfGenerator->filename($exam);

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    public function nextOrderNumber(OrderNumberGenerator $orderNumbers): JsonResponse
    {
        $next = $orderNumbers->current() + 1;

        return response()->json([
            'next_order_number' => $next,
            'next_order_code' => $orderNumbers->format($next),
        ]);
    }

    /** Valores normales precargados para que el medico los revise y ajuste. */
    public function draft(Request $request, MedicalParameterGenerator $parameters): JsonResponse
    {
        $validated = $request->validate([
            'height_cm' => ['required', 'integer', 'min:120', 'max:230'],
            'weight_kg' => ['nullable', 'numeric', 'min:30', 'max:250'],
        ]);

        return response()->json([
            'medical_parameters' => $parameters->generate(
                (int) $validated['height_cm'],
                isset($validated['weight_kg']) ? (float) $validated['weight_kg'] : null,
            ),
            'paraclinicals' => $parameters->paraclinicals(),
        ]);
    }

    public function idealWeight(Request $request, IdealWeightCalculator $weights): JsonResponse
    {
        $validated = $request->validate([
            'height_cm' => ['required', 'integer', 'min:120', 'max:230'],
        ]);

        return response()->json($weights->forHeight((int) $validated['height_cm']));
    }
}
