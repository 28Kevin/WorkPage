<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillingAccountRequest;
use App\Http\Requests\UpdateBillingAccountRequest;
use App\Http\Resources\BillingAccountResource;
use App\Models\BillingAccount;
use App\Models\BillingCompany;
use App\Models\BillingConcept;
use App\Services\BillingAccountGenerator;
use App\Services\BillingAccountPdfGenerator;
use App\Services\OrderNumberGenerator;
use App\Support\MoneyInWords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BillingAccountController extends Controller
{
    use SortsListings;

    private const RELATIONS = ['creator', 'company'];

    /** Columnas por las que se puede ordenar el listado. */
    private const SORTABLE = [
        'number_code' => 'number',
        'company_name' => 'company_name',
        'company_nit' => 'company_nit',
        'issue_date' => 'issue_date',
        'total' => 'total',
    ];

    /** Historial, con busqueda por empresa o por numero. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = BillingAccount::query()
            ->when($request->input('status', 'active') === 'active', fn ($q) => $q->active())
            ->when($request->input('status') === 'annulled', fn ($q) => $q->annulled())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search'));
                $query->where(fn ($q) => $q
                    ->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_nit', 'like', "%{$search}%")
                    ->orWhere('number_code', 'like', "%{$search}%"));
            })
            ->tap(fn ($query) => $this->sorted($query, $request, self::SORTABLE, 'number_code'))
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BillingAccountResource::collection($accounts);
    }

    public function store(
        StoreBillingAccountRequest $request,
        BillingAccountGenerator $generator,
    ): JsonResponse {
        $account = $generator->create($request->validated(), $request->user());

        return BillingAccountResource::make($account)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BillingAccount $account): BillingAccountResource
    {
        return BillingAccountResource::make($account->load(self::RELATIONS));
    }

    public function update(
        UpdateBillingAccountRequest $request,
        BillingAccount $account,
        BillingAccountGenerator $generator,
    ): BillingAccountResource|JsonResponse {
        if ($account->isAnnulled()) {
            return response()->json([
                'message' => 'La cuenta de cobro está anulada y no puede corregirse.',
            ], Response::HTTP_CONFLICT);
        }

        return BillingAccountResource::make($generator->update($account, $request->validated()));
    }

    /** Se anula sin borrar: el consecutivo no se reutiliza. */
    public function destroy(Request $request, BillingAccount $account): JsonResponse
    {
        if ($account->isAnnulled()) {
            return response()->json([
                'message' => 'La cuenta de cobro ya estaba anulada.',
            ], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['reason' => 'motivo de anulación']);

        $account->update([
            'annulled_at' => now(),
            'annulment_reason' => $validated['reason'],
            'annulled_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'La cuenta de cobro fue anulada. Su consecutivo no se reutiliza.',
            'data' => BillingAccountResource::make($account->load(self::RELATIONS))->resolve(),
        ]);
    }

    /** ?inline=1 para la vista previa; sin el parametro, descarga. */
    public function pdf(
        BillingAccount $account,
        BillingAccountPdfGenerator $pdfGenerator,
        Request $request,
    ) {
        $pdf = $pdfGenerator->make($account);
        $filename = $pdfGenerator->filename($account);

        return $request->boolean('inline')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    /**
     * Lo que necesita el formulario al abrirse: el consecutivo que sigue, los
     * conceptos del desplegable y las empresas ya guardadas.
     */
    public function form(OrderNumberGenerator $numbers): JsonResponse
    {
        $next = $numbers->current(OrderNumberGenerator::BILLING_KEY) + 1;

        return response()->json([
            'next_number' => $next,
            'next_number_code' => BillingAccountGenerator::formatNumber($next),
            'concepts' => BillingConcept::active()->ordered()->get(['id', 'name']),
            'companies' => BillingCompany::orderBy('name')->get(['id', 'name', 'nit', 'address', 'city']),
        ]);
    }

    /**
     * Total en numeros y en letras de un importe cualquiera. La pantalla lo
     * usa para mostrar el mismo texto que saldra impreso, sin reimplementar
     * las reglas del castellano en el navegador.
     */
    public function amount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $total = (int) $validated['total'];

        return response()->json([
            'total' => $total,
            'total_label' => MoneyInWords::format($total),
            'total_in_words' => MoneyInWords::spell($total),
        ]);
    }
}
