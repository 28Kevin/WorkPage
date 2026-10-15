<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MailDelivery;
use App\Support\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/** Credenciales del servidor de correo. Restringido a cuentas de desarrollo. */
class MailSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            // La contrasenia nunca sale del servidor: solo si existe.
            'settings' => [...MailSettings::all(), 'has_password' => MailSettings::hasPassword()],
            'status' => MailDelivery::check(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => ['nullable', 'string', 'max:150'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:150'],
            // Vacia conserva la guardada; el formulario nunca la recibe.
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'from_address' => ['nullable', 'email', 'max:150'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ], [], [
            'host' => 'servidor',
            'port' => 'puerto',
            'username' => 'usuario',
            'password' => 'contraseña',
            'encryption' => 'cifrado',
            'from_address' => 'correo remitente',
            'from_name' => 'nombre del remitente',
        ]);

        MailSettings::save($validated);
        MailSettings::apply();

        return response()->json([
            'message' => 'Configuración de correo guardada.',
            'settings' => [...MailSettings::all(), 'has_password' => MailSettings::hasPassword()],
            'status' => MailDelivery::check(),
        ]);
    }

    public function forgetPassword(): JsonResponse
    {
        MailSettings::forgetPassword();

        return response()->json(['message' => 'Contraseña eliminada.']);
    }

    /**
     * Envia un correo de prueba. Es la unica forma de saber si las
     * credenciales sirven: que esten completas no significa que el servidor
     * las acepte.
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
        ], [], ['email' => 'correo de destino']);

        $status = MailDelivery::check();

        if (! $status['deliverable']) {
            return response()->json(['message' => $status['reason']], 422);
        }

        try {
            Mail::raw(
                'Si recibe este mensaje, el envío de correo de la plataforma está funcionando.',
                fn ($message) => $message->to($validated['email'])->subject('Prueba de correo saliente'),
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'El servidor de correo rechazó el envío: '.$exception->getMessage(),
            ], 409);
        }

        return response()->json(['message' => "Correo de prueba enviado a {$validated['email']}."]);
    }
}
