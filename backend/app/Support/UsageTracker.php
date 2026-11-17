<?php

namespace App\Support;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cuenta eventos de uso (ingresos, consultas, descargas) gastando lo minimo de
 * base de datos: los conteos se acumulan en la cache de archivo y se vuelcan
 * agregados por hora, en una sola consulta, cada FLUSH_SECONDS como mucho.
 *
 * Se usa el almacen 'file' a proposito: en produccion la cache por defecto es
 * 'database' y escribiria en la base en cada evento, justo lo que se evita.
 * Si el contenedor se reinicia se pierden, como mucho, los ultimos minutos.
 */
class UsageTracker
{
    public const LOGIN = 'login.success';
    public const LOGIN_FAILED = 'login.failed';
    public const PUBLIC_SEARCH = 'public.search';
    public const PUBLIC_VERIFY = 'public.verify';
    public const PDF_DOWNLOAD = 'pdf.download';

    private const BUFFER_KEY = 'usage.buffer';
    private const FLUSHED_KEY = 'usage.flushed_at';
    private const FLUSH_SECONDS = 600;

    public static function store(): Repository
    {
        return Cache::store(config('cache.usage_store'));
    }

    /** Suma un evento. Nunca interrumpe la peticion si algo falla. */
    public static function record(string $metric, string $subject = ''): void
    {
        try {
            $bucket = now()->startOfHour()->toDateTimeString();
            $key = $bucket.'|'.$metric.'|'.mb_substr($subject, 0, 80);

            self::store()->lock('usage.buffer.lock', 5)->block(2, function () use ($key) {
                $buffer = self::store()->get(self::BUFFER_KEY, []);
                $buffer[$key] = ($buffer[$key] ?? 0) + 1;
                self::store()->forever(self::BUFFER_KEY, $buffer);
            });

            if (now()->timestamp - (int) self::store()->get(self::FLUSHED_KEY, 0) >= self::FLUSH_SECONDS) {
                self::flush();
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo registrar el uso.', ['metric' => $metric, 'error' => $exception->getMessage()]);
        }
    }

    /** Vuelca lo acumulado a la base con un unico upsert que suma a lo existente. */
    public static function flush(): void
    {
        $rows = [];

        self::store()->lock('usage.buffer.lock', 5)->block(2, function () use (&$rows) {
            $buffer = self::store()->get(self::BUFFER_KEY, []);
            self::store()->forget(self::BUFFER_KEY);
            self::store()->forever(self::FLUSHED_KEY, now()->timestamp);

            foreach ($buffer as $key => $count) {
                [$bucket, $metric, $subject] = explode('|', $key, 3);
                $rows[] = compact('bucket', 'metric', 'subject', 'count');
            }
        });

        if ($rows === []) {
            return;
        }

        try {
            DB::table('usage_events')->upsert(
                $rows,
                ['bucket', 'metric', 'subject'],
                ['count' => DB::raw('usage_events.count + excluded.count')],
            );
        } catch (Throwable $exception) {
            // Si la base falla, los conteos vuelven al buffer para el siguiente intento.
            self::store()->lock('usage.buffer.lock', 5)->block(2, function () use ($rows) {
                $buffer = self::store()->get(self::BUFFER_KEY, []);

                foreach ($rows as $row) {
                    $key = $row['bucket'].'|'.$row['metric'].'|'.$row['subject'];
                    $buffer[$key] = ($buffer[$key] ?? 0) + $row['count'];
                }

                self::store()->forever(self::BUFFER_KEY, $buffer);
            });

            Log::warning('No se pudo volcar el uso a la base.', ['error' => $exception->getMessage()]);
        }
    }
}
