<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tablero de uso de la plataforma. Solo para cuentas de desarrollo. */
class UsageController extends Controller
{
    /** Rango por defecto de cada agrupacion, cuando no se indican fechas. */
    private const DEFAULT_SPAN = [
        'hour' => '-48 hours',
        'day' => '-30 days',
        'week' => '-26 weeks',
        'month' => '-12 months',
    ];

    public function index(Request $request, UsageReport $report): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(UsageReport::PERIODS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [], [
            'period' => 'agrupación',
            'from' => 'fecha inicial',
            'to' => 'fecha final',
        ]);

        $period = $validated['period'] ?? 'day';

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : CarbonImmutable::now();

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : CarbonImmutable::parse($to->modify(self::DEFAULT_SPAN[$period]));

        return response()->json($report->build($from, $to, $period));
    }
}
