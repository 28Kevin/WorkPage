<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\DatabaseBackup;
use App\Support\MailDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Respaldo de la base. Restringido a cuentas de desarrollo. */
class BackupController extends Controller
{
    /** Intentos que muestra el panel. */
    private const HISTORY = 10;

    /**
     * Estado del respaldo: si el correo puede entregarse y como fueron los
     * ultimos intentos. Es lo que convierte un fallo silencioso en un aviso.
     */
    public function index(): JsonResponse
    {
        $runs = BackupRun::with('creator')->latest('id')->limit(self::HISTORY)->get();
        $lastGood = BackupRun::where('status', BackupRun::OK)->where('delivered', true)->latest('id')->first();

        return response()->json([
            'mail' => MailDelivery::check(),
            'configured_recipient' => config('backup.notifications.mail.to'),
            'last_successful_at' => $lastGood?->created_at?->toIso8601String(),
            'runs' => $runs->map(fn (BackupRun $run) => [
                'id' => $run->id,
                'status' => $run->status,
                'channel' => $run->channel,
                'delivered' => $run->delivered,
                'mailer' => $run->mailer,
                'recipient' => $run->recipient,
                'filename' => $run->filename,
                'bytes' => $run->bytes,
                'error' => $run->error,
                'created_at' => $run->created_at?->toIso8601String(),
                'created_by' => $run->creator?->name,
            ])->all(),
        ]);
    }

    /**
     * Genera el respaldo y lo entrega como descarga.
     *
     * Es la via principal: no depende del servidor de correo ni del limite de
     * tamanio de un adjunto.
     */
    public function download(Request $request, DatabaseBackup $backup): BinaryFileResponse|JsonResponse
    {
        try {
            $file = $backup->generate();
        } catch (Throwable $exception) {
            $this->record(BackupRun::FAILED, $request, null, channel: BackupRun::DOWNLOAD, error: $exception->getMessage());

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        $this->record(
            BackupRun::OK,
            $request,
            null,
            // Una descarga llega a su destino por definicion: la recibe quien
            // la pidio, sin intermediarios que puedan fallar en silencio.
            delivered: true,
            channel: BackupRun::DOWNLOAD,
            filename: $file['filename'],
            bytes: $file['bytes'],
        );

        return response()->download($file['path'], $file['filename']);
    }

    /**
     * Genera el respaldo y lo envia al correo indicado. Deja una copia fuera
     * del servidor, que es lo que la descarga no cubre.
     */
    public function store(Request $request, DatabaseBackup $backup): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:150'],
        ], [], ['email' => 'correo de destino']);

        $recipient = $validated['email'] ?? config('backup.notifications.mail.to');

        if (blank($recipient)) {
            return response()->json([
                'message' => 'No hay un correo de destino. Indíquelo o configure BACKUP_MAIL_TO.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $backup->sendTo($recipient);
        } catch (RuntimeException $exception) {
            $this->record(BackupRun::FAILED, $request, $recipient, channel: BackupRun::MAIL, error: $exception->getMessage());

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (Throwable $exception) {
            // Un fallo del servidor de correo tambien tiene que quedar anotado.
            $this->record(BackupRun::FAILED, $request, $recipient, channel: BackupRun::MAIL, error: $exception->getMessage());

            return response()->json([
                'message' => 'El respaldo no pudo enviarse: '.$exception->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        $this->record(
            BackupRun::OK,
            $request,
            $recipient,
            delivered: $result['delivered'],
            channel: BackupRun::MAIL,
            mailer: $result['mailer'],
            filename: $result['filename'],
            bytes: $result['bytes'],
            error: $result['warning'],
        );

        /*
         * El mensaje no afirma que se envio cuando el driver no entrega nada:
         * decirlo seria dar por cubierto un respaldo que no salio del servidor.
         */
        return response()->json([
            'message' => $result['delivered']
                ? "El respaldo se envió a {$recipient}."
                : "El respaldo se generó, pero NO se envió a {$recipient}.",
            'delivered' => $result['delivered'],
            'warning' => $result['warning'],
            'filename' => $result['filename'],
            'bytes' => $result['bytes'],
        ]);
    }

    private function record(
        string $status,
        Request $request,
        ?string $recipient,
        bool $delivered = false,
        string $channel = BackupRun::MAIL,
        ?string $mailer = null,
        ?string $filename = null,
        ?int $bytes = null,
        ?string $error = null,
    ): void {
        BackupRun::create([
            'status' => $status,
            'channel' => $channel,
            'delivered' => $delivered,
            'mailer' => $mailer ?? config('mail.default'),
            'recipient' => $recipient,
            'filename' => $filename,
            'bytes' => $bytes,
            'error' => $error,
            'created_by' => $request->user()?->id,
        ]);
    }
}
