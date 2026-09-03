<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use RuntimeException;

class SendDatabaseBackup extends Command
{
    protected $signature = 'respaldo:enviar {correo? : Destinatario; por defecto BACKUP_MAIL_TO}';

    protected $description = 'Genera el respaldo de la base y lo envía por correo como archivo adjunto';

    public function handle(DatabaseBackup $backup): int
    {
        $recipient = $this->argument('correo') ?? config('backup.notifications.mail.to');

        if (blank($recipient)) {
            $this->error('No hay destinatario. Pase un correo o defina BACKUP_MAIL_TO.');

            return self::FAILURE;
        }

        $this->info("Generando el respaldo y enviándolo a {$recipient}…");

        try {
            $result = $backup->sendTo($recipient);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Enviado: %s (%s KB)',
            $result['filename'],
            number_format($result['bytes'] / 1024, 0, ',', '.'),
        ));

        return self::SUCCESS;
    }
}
