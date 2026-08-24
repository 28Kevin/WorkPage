<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalExamRequest;
use App\Http\Resources\MedicalExamListResource;
use App\Http\Resources\MedicalExamResource;
use App\Models\MedicalExam;
use App\Services\ExamGenerator;
use App\Services\ExamPdfGenerator;
use App\Services\IdealWeightCalculator;
use App\Services\OrderNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MedicalExamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $exams = MedicalExam::query()
            ->with('city')
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

    public function store(StoreMedicalExamRequest $request, ExamGenerator $generator, IdealWeightCalculator $weights): JsonResponse
    {
        $exam = $generator->create($request->validatedWithDefaults($weights), $request->user());

        return MedicalExamResource::make($exam)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(MedicalExam $exam): MedicalExamResource
    {
        return MedicalExamResource::make($exam->load(['eps', 'arl', 'city', 'risks', 'creator']));
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

    public function idealWeight(Request $request, IdealWeightCalculator $weights): JsonResponse
    {
        $validated = $request->validate([
            'height_cm' => ['required', 'integer', 'min:120', 'max:230'],
        ]);

        return response()->json($weights->forHeight((int) $validated['height_cm']));
    }
}
