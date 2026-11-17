<?php

namespace App\Services;

use App\Models\BillingAccount;
use App\Models\FoodHandlerRecord;
use App\Models\MedicalExam;
use App\Models\TrainingCertificate;
use App\Models\User;
use App\Support\UsageTracker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Uso de la plataforma: cuantos documentos se emitieron, repartidos por
 * periodo y por hora del dia.
 *
 * El agrupado se hace en PHP y no en SQL a proposito: las funciones de fecha
 * de PostgreSQL y SQLite no coinciden, y las pruebas corren sobre SQLite
 * mientras produccion usa PostgreSQL. El volumen de un centro medico cabe de
 * sobra en memoria, asi que no compensa mantener dos dialectos.
 */
class UsageReport
{
    /** Documentos que cuentan como uso, con su etiqueta. */
    public const DOCUMENTS = [
        'medical_exam' => ['label' => 'Exámenes ocupacionales', 'model' => MedicalExam::class],
        'food_handler' => ['label' => 'Manipulación de alimentos', 'model' => FoodHandlerRecord::class],
        'training_certificate' => ['label' => 'Certificados de capacitación', 'model' => TrainingCertificate::class],
        'billing_account' => ['label' => 'Cuentas de cobro', 'model' => BillingAccount::class],
    ];

    /** Agrupaciones que ofrece el tablero. */
    public const PERIODS = ['hour', 'day', 'week', 'month'];

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, string $period): array
    {
        $timestamps = $this->timestamps($from, $to);

        return [
            'range' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'period' => $period,
            ],
            'documents' => $this->documentLabels(),
            'totals' => $this->totals($timestamps),
            'series' => $this->series($timestamps, $from, $to, $period),
            'by_hour' => $this->byHour($timestamps),
            'by_weekday' => $this->byWeekday($timestamps),
            'busiest_hour' => $this->busiestHour($timestamps),
            'heatmap' => $this->heatmap($timestamps),
            'activity' => $this->activity($from, $to, $period),
        ];
    }

    /** Eventos de uso que muestra el tablero, con su etiqueta. */
    public const ACTIVITY = [
        UsageTracker::LOGIN => 'Ingresos al sistema',
        UsageTracker::PUBLIC_SEARCH => 'Consultas públicas',
        UsageTracker::PUBLIC_VERIFY => 'Verificaciones por código',
        UsageTracker::PDF_DOWNLOAD => 'Descargas de PDF',
        UsageTracker::LOGIN_FAILED => 'Ingresos fallidos',
    ];

    /** Nombres legibles de los modelos que cuenta 'pdf.download'. */
    private const PDF_TYPES = [
        'MedicalExam' => 'Exámenes ocupacionales',
        'FoodHandlerRecord' => 'Manipulación de alimentos',
        'TrainingCertificate' => 'Certificados de capacitación',
        'BillingAccount' => 'Cuentas de cobro',
    ];

    /**
     * Matriz dia de la semana × hora: responde a que horas y que dias se
     * suben mas documentos. Filas de lunes a domingo, 24 columnas cada una.
     *
     * @param  array<string, Collection<int, CarbonImmutable>>  $timestamps
     * @return array<string, mixed>
     */
    private function heatmap(array $timestamps): array
    {
        $cells = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($timestamps as $dates) {
            foreach ($dates as $date) {
                $cells[(int) $date->format('N') - 1][(int) $date->format('G')]++;
            }
        }

        return [
            'weekdays' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            'cells' => $cells,
            'max' => max(array_map('max', $cells)),
        ];
    }

    /**
     * Ingresos, consultas, verificaciones y descargas, leidos de la tabla ya
     * agregada por hora: una sola consulta para todo el rango.
     *
     * @return array<string, mixed>
     */
    private function activity(CarbonImmutable $from, CarbonImmutable $to, string $period): array
    {
        $events = DB::table('usage_events')
            ->whereBetween('bucket', [$from->startOfHour(), $to])
            ->get(['bucket', 'metric', 'subject', 'count']);

        $totals = array_fill_keys(array_keys(self::ACTIVITY), 0);
        $buckets = [];

        for ($cursor = $this->floor($from, $period); $cursor <= $to; $cursor = $this->advance($cursor, $period)) {
            $buckets[$cursor->toIso8601String()] = [
                'bucket' => $cursor->toIso8601String(),
                'label' => $this->label($cursor, $period),
                ...array_fill_keys(array_keys(self::ACTIVITY), 0),
            ];
        }

        $verifications = [];
        $logins = [];
        $downloads = [];

        foreach ($events as $event) {
            if (! isset($totals[$event->metric])) {
                continue;
            }

            $count = (int) $event->count;
            $totals[$event->metric] += $count;

            $bucket = $this->floor(CarbonImmutable::parse($event->bucket), $period)->toIso8601String();

            if (isset($buckets[$bucket])) {
                $buckets[$bucket][$event->metric] += $count;
            }

            match ($event->metric) {
                UsageTracker::PUBLIC_VERIFY => $verifications[$event->subject] = ($verifications[$event->subject] ?? 0) + $count,
                UsageTracker::LOGIN => $logins[$event->subject] = ($logins[$event->subject] ?? 0) + $count,
                UsageTracker::PDF_DOWNLOAD => $downloads[$event->subject] = ($downloads[$event->subject] ?? 0) + $count,
                default => null,
            };
        }

        return [
            'metrics' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::ACTIVITY[$key]],
                array_keys(self::ACTIVITY),
            ),
            'totals' => $totals,
            'series' => array_values($buckets),
            'top_documents' => $this->topDocuments($verifications),
            'top_users' => $this->topUsers($logins),
            'downloads_by_type' => $this->downloadsByType($downloads),
        ];
    }

    /**
     * Los documentos mas consultados por su codigo QR. Se resuelven solo los
     * diez primeros, con una consulta por tipo de documento.
     *
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function topDocuments(array $counts): array
    {
        arsort($counts);
        $counts = array_slice($counts, 0, 10, true);

        if ($counts === []) {
            return [];
        }

        $codes = array_map('strval', array_keys($counts));
        $found = [];

        foreach ([
            MedicalExam::class => 'Examen ocupacional',
            FoodHandlerRecord::class => 'Manipulación de alimentos',
            TrainingCertificate::class => 'Certificado de capacitación',
        ] as $model => $type) {
            $model::query()
                ->whereIn('verification_code', $codes)
                ->get(['verification_code', 'order_code'])
                ->each(function ($document) use (&$found, $type) {
                    $found[$document->verification_code] = ['order_code' => $document->order_code, 'type' => $type];
                });
        }

        $rows = [];

        foreach ($counts as $code => $count) {
            $rows[] = [
                'order_code' => $found[$code]['order_code'] ?? 'Código no encontrado',
                'type' => $found[$code]['type'] ?? '—',
                'count' => $count,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function topUsers(array $counts): array
    {
        arsort($counts);
        $counts = array_slice($counts, 0, 10, true);

        $names = User::query()->whereIn('id', array_keys($counts))->pluck('name', 'id');

        $rows = [];

        foreach ($counts as $id => $count) {
            $rows[] = ['name' => $names[$id] ?? 'Usuario eliminado', 'count' => $count];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function downloadsByType(array $counts): array
    {
        return array_map(
            fn (string $model) => ['label' => self::PDF_TYPES[$model], 'count' => $counts[$model] ?? 0],
            array_keys(self::PDF_TYPES),
        );
    }

    /**
     * Fechas de creacion de cada documento dentro del rango.
     *
     * @return array<string, Collection<int, CarbonImmutable>>
     */
    private function timestamps(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = [];

        foreach (self::DOCUMENTS as $key => $document) {
            $result[$key] = $document['model']::query()
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('created_at')
                ->pluck('created_at')
                ->map(fn ($date) => CarbonImmutable::parse($date));
        }

        return $result;
    }

    /** @return array<int, array<string, string>> */
    private function documentLabels(): array
    {
        return array_map(
            fn (string $key) => ['key' => $key, 'label' => self::DOCUMENTS[$key]['label']],
            array_keys(self::DOCUMENTS),
        );
    }

    /**
     * @param  array<string, Collection<int, CarbonImmutable>>  $timestamps
     * @return array<string, int>
     */
    private function totals(array $timestamps): array
    {
        $totals = array_map(fn (Collection $dates) => $dates->count(), $timestamps);
        $totals['all'] = array_sum($totals);

        return $totals;
    }

    /**
     * Serie temporal con un punto por intervalo, incluidos los vacios: una
     * grafica con huecos miente sobre el ritmo de uso.
     *
     * @param  array<string, Collection<int, CarbonImmutable>>  $timestamps
     * @return array<int, array<string, mixed>>
     */
    private function series(array $timestamps, CarbonImmutable $from, CarbonImmutable $to, string $period): array
    {
        $buckets = [];

        for ($cursor = $this->floor($from, $period); $cursor <= $to; $cursor = $this->advance($cursor, $period)) {
            $buckets[$cursor->toIso8601String()] = [
                'bucket' => $cursor->toIso8601String(),
                'label' => $this->label($cursor, $period),
                ...array_fill_keys(array_keys(self::DOCUMENTS), 0),
                'total' => 0,
            ];
        }

        foreach ($timestamps as $key => $dates) {
            foreach ($dates as $date) {
                $bucket = $this->floor($date, $period)->toIso8601String();

                if (! isset($buckets[$bucket])) {
                    continue;
                }

                $buckets[$bucket][$key]++;
                $buckets[$bucket]['total']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * Reparto por hora del dia (0-23): responde a que horas se registra mas.
     *
     * @param  array<string, Collection<int, CarbonImmutable>>  $timestamps
     * @return array<int, array<string, mixed>>
     */
    private function byHour(array $timestamps): array
    {
        $hours = array_fill(0, 24, 0);

        foreach ($timestamps as $dates) {
            foreach ($dates as $date) {
                $hours[(int) $date->format('G')]++;
            }
        }

        return array_map(
            fn (int $hour) => [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'count' => $hours[$hour],
            ],
            range(0, 23),
        );
    }

    /**
     * Reparto por dia de la semana, empezando en lunes.
     *
     * @param  array<string, Collection<int, CarbonImmutable>>  $timestamps
     * @return array<int, array<string, mixed>>
     */
    private function byWeekday(array $timestamps): array
    {
        $names = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $days = array_fill(0, 7, 0);

        foreach ($timestamps as $dates) {
            foreach ($dates as $date) {
                // ISO: 1 = lunes.
                $days[(int) $date->format('N') - 1]++;
            }
        }

        return array_map(
            fn (int $index) => ['weekday' => $index + 1, 'label' => $names[$index], 'count' => $days[$index]],
            range(0, 6),
        );
    }

    /**
     * Hora con mas registros. Devuelve null si no hubo ninguno, para que el
     * tablero no afirme que "la hora pico es la medianoche" sobre cero datos.
     *
     * @param  array<string, Collection<int, CarbonImmutable>>  $timestamps
     * @return array<string, mixed>|null
     */
    private function busiestHour(array $timestamps): ?array
    {
        $hours = collect($this->byHour($timestamps));

        if ($hours->sum('count') === 0) {
            return null;
        }

        return $hours->sortByDesc('count')->first();
    }

    private function floor(CarbonInterface $date, string $period): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date);

        return match ($period) {
            'hour' => $date->startOfHour(),
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }

    private function advance(CarbonImmutable $date, string $period): CarbonImmutable
    {
        return match ($period) {
            'hour' => $date->addHour(),
            'week' => $date->addWeek(),
            'month' => $date->addMonth(),
            default => $date->addDay(),
        };
    }

    private function label(CarbonImmutable $date, string $period): string
    {
        return match ($period) {
            'hour' => $date->format('d/m H:i'),
            'week' => 'Sem. '.$date->format('d/m'),
            'month' => $date->translatedFormat('M Y'),
            default => $date->format('d/m'),
        };
    }
}
