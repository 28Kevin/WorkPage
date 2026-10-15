<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Credenciales del servidor de correo, configurables desde el panel.
 *
 * No van en el grupo de Branding a proposito: ese se expone sin sesion para
 * que la portada pinte los colores, y aqui hay credenciales.
 *
 * La contrasenia se guarda cifrada y nunca se devuelve al navegador: el
 * formulario solo sabe si existe o no.
 */
class MailSettings
{
    private const PREFIX = 'mail.';

    /** Campos visibles del formulario. Los secretos van aparte. */
    public const FIELDS = ['transport', 'host', 'port', 'username', 'encryption', 'from_address', 'from_name'];

    /** Vias de salida que ofrece el panel. */
    public const TRANSPORTS = ['smtp', 'resend'];

    /** Valores que no se devuelven nunca al navegador. */
    private const PASSWORD = 'password';

    private const API_KEY = 'api_key';

    /**
     * Ajustes guardados, sin la contrasenia.
     *
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        $stored = self::stored();

        return array_combine(
            self::FIELDS,
            array_map(fn (string $field) => $stored[self::PREFIX.$field] ?? null, self::FIELDS),
        );
    }

    public static function hasPassword(): bool
    {
        return filled(self::stored()[self::PREFIX.self::PASSWORD] ?? null);
    }

    public static function hasApiKey(): bool
    {
        return filled(self::stored()[self::PREFIX.self::API_KEY] ?? null);
    }

    /** Via elegida en el panel. */
    public static function transport(): string
    {
        $transport = self::all()['transport'];

        return in_array($transport, self::TRANSPORTS, true) ? $transport : 'smtp';
    }

    /** ¿Hay algo configurado desde el panel que debamos aplicar? */
    public static function configured(): bool
    {
        return self::transport() === 'resend'
            ? self::hasApiKey()
            : filled(self::all()['host']);
    }

    /**
     * Guarda los ajustes. Una contrasenia vacia conserva la que ya habia: el
     * formulario nunca la recibe, asi que no puede reenviarla.
     *
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $flat = [];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $flat[self::PREFIX.$field] = $values[$field] === null ? '' : (string) $values[$field];
            }
        }

        foreach ([self::PASSWORD, self::API_KEY] as $secret) {
            if (filled($values[$secret] ?? null)) {
                $flat[self::PREFIX.$secret] = Crypt::encryptString((string) $values[$secret]);
            }
        }

        Setting::putMany($flat);
    }

    /** Borra los secretos guardados. */
    public static function forgetPassword(): void
    {
        Setting::putMany([
            self::PREFIX.self::PASSWORD => '',
            self::PREFIX.self::API_KEY => '',
        ]);
    }

    /**
     * Vuelca los ajustes guardados sobre la configuracion de correo.
     *
     * Se llama al arrancar la aplicacion para que tanto el envio como la
     * comprobacion previa miren exactamente lo mismo; si solo se aplicara al
     * enviar, el panel podria decir "configurado" leyendo el .env mientras el
     * envio usa otra cosa.
     */
    public static function apply(): void
    {
        try {
            if (! self::configured()) {
                return;
            }
        } catch (Throwable) {
            // Durante las migraciones iniciales no existe la tabla todavia.
            return;
        }

        $settings = self::all();

        /*
         * Resend viaja por HTTPS en vez de SMTP. Hace falta donde el proveedor
         * de hosting bloquea los puertos de correo saliente, que es lo normal
         * en los planes gratuitos.
         */
        if (self::transport() === 'resend') {
            config([
                'mail.default' => 'resend',
                'services.resend.key' => self::secret(self::API_KEY),
            ]);

            self::applyFrom($settings);

            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => (int) ($settings['port'] ?: 587),
            'mail.mailers.smtp.username' => $settings['username'] ?: null,
            'mail.mailers.smtp.password' => self::password(),
            /*
             * Laravel 13 configura el cifrado con 'scheme', no con
             * 'encryption': 'smtps' es TLS implicito (puerto 465) y 'smtp'
             * deja que se negocie STARTTLS (puerto 587). Escribir
             * 'encryption' no haria nada: esa clave ya no existe.
             */
            'mail.mailers.smtp.scheme' => $settings['encryption'] === 'ssl' ? 'smtps' : 'smtp',
        ]);

        self::applyFrom($settings);
    }

    /** @param array<string, string|null> $settings */
    private static function applyFrom(array $settings): void
    {
        if (filled($settings['from_address'])) {
            config(['mail.from.address' => $settings['from_address']]);
        }

        if (filled($settings['from_name'])) {
            config(['mail.from.name' => $settings['from_name']]);
        }
    }

    private static function password(): ?string
    {
        return self::secret(self::PASSWORD);
    }

    /** Secreto descifrado, o null si no hay o si no se puede descifrar. */
    private static function secret(string $name): ?string
    {
        $stored = self::stored()[self::PREFIX.$name] ?? null;

        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // Cambio de APP_KEY: la guardada ya no sirve, hay que volver a ponerla.
            return null;
        }
    }

    /** @return array<string, string|null> */
    private static function stored(): array
    {
        return Setting::map();
    }
}
