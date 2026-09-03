<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BackupController extends Controller
{
    /**
     * Genera el respaldo y lo envia al correo indicado. Es sincrono: el volcado
     * de una base pequena tarda unos segundos y evita montar una cola.
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
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => "El respaldo se envió a {$recipient}.",
            'filename' => $result['filename'],
            'bytes' => $result['bytes'],
        ]);
    }
}
