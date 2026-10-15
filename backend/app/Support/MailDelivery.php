<?php

namespace App\Support;

/**
 * Dice si el correo saliente puede entregarse de verdad.
 *
 * Laravel no distingue entre "enviado" y "escrito en el log": con el driver
 * 'log' o 'array', Mail::send() termina sin error y no sale nada del servidor.
 * Para un respaldo eso es peligroso, porque deja creyendo que hay una copia a
 * salvo cuando no la hay.
 */
class MailDelivery
{
    /** Drivers que no entregan nada: solo sirven para desarrollo. */
    private const NOT_DELIVERED = ['log', 'array', 'null'];

    /**
     * @return array{deliverable: bool, mailer: string, reason: string|null}
     */
    public static function check(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, self::NOT_DELIVERED, true)) {
            return [
                'deliverable' => false,
                'mailer' => $mailer,
                'reason' => "El correo está configurado como «{$mailer}»: el mensaje se escribe en el "
                    .'registro del servidor y no se envía a nadie. Configure MAIL_MAILER=smtp y las '
                    .'credenciales del servidor de correo.',
            ];
        }

        if ($mailer === 'smtp' && blank(config('mail.mailers.smtp.host'))) {
            return [
                'deliverable' => false,
                'mailer' => $mailer,
                'reason' => 'Falta el servidor de correo (MAIL_HOST). Sin él no se puede entregar el respaldo.',
            ];
        }

        if (blank(config('mail.from.address'))) {
            return [
                'deliverable' => false,
                'mailer' => $mailer,
                'reason' => 'Falta el remitente (MAIL_FROM_ADDRESS). El servidor de correo rechazará el mensaje.',
            ];
        }

        return ['deliverable' => true, 'mailer' => $mailer, 'reason' => null];
    }
}
