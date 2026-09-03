<?php

namespace App\Services;

use App\Mail\DatabaseBackupMail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Genera el volcado de la base con spatie/laravel-backup y lo envia por correo.
 *
 * El respaldo no reemplaza al del proveedor de la base: es una copia que queda
 * fuera de la infraestructura, para poder restaurar aunque se pierda la cuenta.
 */
class DatabaseBackup
{
    /** Limite habitual de adjuntos en los proveedores de correo. */
    private const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

    /**
     * @return array{filename: string, bytes: int}
     *
     * @throws RuntimeException si el volcado falla o pesa demasiado
     */
    public function sendTo(string $recipient): array
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'No fue posible generar el respaldo. Revise que pg_dump esté disponible en el servidor.',
            );
        }

        $backup = $this->latestBackup();

        if ($backup === null) {
            throw new RuntimeException('El respaldo se ejecutó pero no se encontró el archivo generado.');
        }

        [$path, $bytes] = $backup;

        if ($bytes > self::MAX_ATTACHMENT_BYTES) {
            throw new RuntimeException(sprintf(
                'El respaldo pesa %s y supera el límite de %s por correo. Descárguelo desde el servidor.',
                $this->readable($bytes),
                $this->readable(self::MAX_ATTACHMENT_BYTES),
            ));
        }

        $filename = basename($path);

        Mail::to($recipient)->send(new DatabaseBackupMail(
            Storage::disk('local')->path($path),
            $filename,
            $bytes,
        ));

        return ['filename' => $filename, 'bytes' => $bytes];
    }

    /**
     * El archivo más reciente de la carpeta que usa el paquete.
     *
     * @return array{0: string, 1: int}|null
     */
    private function latestBackup(): ?array
    {
        $disk = Storage::disk('local');
        $folder = config('backup.backup.name');

        $files = collect($disk->files($folder))
            ->filter(fn (string $file) => str_ends_with($file, '.zip'))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file));

        $latest = $files->first();

        return $latest === null ? null : [$latest, $disk->size($latest)];
    }

    private function readable(int $bytes): string
    {
        return number_format($bytes / 1048576, 1, ',', '.').' MB';
    }
}
