<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingCertificateRequest;
use App\Http\Requests\UpdateTrainingCertificateRequest;
use App\Http\Resources\TrainingCertificateListResource;
use App\Http\Resources\TrainingCertificateResource;
use App\Models\TrainingCertificate;
use App\Services\OrderNumberGenerator;
use App\Services\TrainingCertificateGenerator;
use App\Services\TrainingCertificatePdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TrainingCertificateController extends Controller
{
    use SortsListings;

    private const RELATIONS = ['creator'];

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = [
        'order_code' => 'order_number',
        'participant_name' => 'participant_name',
        'document_number' => 'document_number',
        'course' => 'course',
        'hours' => 'hours',
        'end_date' => 'end_date',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $certificates = TrainingCertificate::query()
            ->when($request->input('status', 'active') === 'active', fn ($q) => $q->active())
            ->when($request->input('status') === 'annulled', fn ($q) => $q->annulled())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search'));
                $query->where(fn ($q) => $q
                    ->where('participant_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhere('order_code', 'like', "%{$search}%")
                    ->orWhere('course', 'like', "%{$search}%"));
            })
            ->tap(fn ($query) => $this->sorted($query, $request, self::SORTABLE, 'order_code'))
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TrainingCertificateListResource::collection($certificates);
    }

    public function store(
        StoreTrainingCertificateRequest $request,
        TrainingCertificateGenerator $generator,
    ): JsonResponse {
        $certificate = $generator->create($request->validated(), $request->user());

        return TrainingCertificateResource::make($certificate)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TrainingCertificate $certificate): TrainingCertificateResource
    {
        return TrainingCertificateResource::make($certificate->load(self::RELATIONS));
    }

    public function update(
        UpdateTrainingCertificateRequest $request,
        TrainingCertificate $certificate,
        TrainingCertificateGenerator $generator,
    ): TrainingCertificateResource|JsonResponse {
        if ($certificate->isAnnulled()) {
            return response()->json([
                'message' => 'El certificado está anulado y no puede corregirse.',
            ], Response::HTTP_CONFLICT);
        }

        return TrainingCertificateResource::make($generator->update($certificate, $request->validated()));
    }

    /** Anula sin borrar: el QR ya impreso debe seguir respondiendo. */
    public function destroy(Request $request, TrainingCertificate $certificate): JsonResponse
    {
        if ($certificate->isAnnulled()) {
            return response()->json([
                'message' => 'El certificado ya estaba anulado.',
            ], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['reason' => 'motivo de anulación']);

        $certificate->update([
            'annulled_at' => now(),
            'annulment_reason' => $validated['reason'],
            'annulled_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'El certificado fue anulado. El código QR seguirá respondiendo como documento sin validez.',
            'data' => TrainingCertificateResource::make($certificate->load(self::RELATIONS))->resolve(),
        ]);
    }

    /** ?inline=1 para la vista previa; sin el parametro, descarga. */
    public function pdf(
        TrainingCertificate $certificate,
        TrainingCertificatePdfGenerator $pdfGenerator,
        Request $request,
    ) {
        $pdf = $pdfGenerator->make($certificate);
        $filename = $pdfGenerator->filename($certificate);

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    public function nextOrderNumber(OrderNumberGenerator $orderNumbers): JsonResponse
    {
        $next = $orderNumbers->current(OrderNumberGenerator::TRAINING_KEY) + 1;

        return response()->json([
            'next_order_number' => $next,
            'next_order_code' => TrainingCertificateGenerator::formatCode($next),
        ]);
    }
}
