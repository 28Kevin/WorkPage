<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageReport;
use App\Support\UsageTracker;
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

    /** Tiempo que se reutiliza un informe ya calculado. */
    private const CACHE_SECONDS = 600;

    public function index(Request $request, UsageReport $report): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(UsageReport::PERIODS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'fresh' => ['nullable', 'boolean'],
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

        /*
         * El informe se guarda en la cache de archivo (no en la de base de
         * datos): repetir la vista o cambiar de pestaña no consulta la base.
         * 'fresh' fuerza el recalculo y vuelca antes los eventos pendientes.
         */
        $key = 'usage.report.'.md5($period.'|'.$from->startOfHour()->toIso8601String().'|'.$to->startOfHour()->toIso8601String());
        $cache = UsageTracker::store();

        if ($request->boolean('fresh')) {
            $cache->forget($key);
        }

        $payload = $cache->remember($key, self::CACHE_SECONDS, function () use ($report, $from, $to, $period) {
            UsageTracker::flush();

            return [...$report->build($from, $to, $period), 'generated_at' => now()->toIso8601String()];
        });

        return response()->json([...$payload, 'cache_seconds' => self::CACHE_SECONDS]);
    }
}
