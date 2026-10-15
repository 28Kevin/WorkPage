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

    /** Campos visibles del formulario. La contrasenia va aparte. */
    public const FIELDS = ['host', 'port', 'username', 'encryption', 'from_address', 'from_name'];

    private const PASSWORD = 'password';

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

    /** ¿Hay un servidor configurado desde el panel? */
    public static function configured(): bool
    {
        return filled(self::all()['host']);
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

        if (filled($values[self::PASSWORD] ?? null)) {
            $flat[self::PREFIX.self::PASSWORD] = Crypt::encryptString((string) $values[self::PASSWORD]);
        }

        Setting::putMany($flat);
    }

    /** Borra la contrasenia guardada. */
    public static function forgetPassword(): void
    {
        Setting::putMany([self::PREFIX.self::PASSWORD => '']);
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

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => (int) ($settings['port'] ?: 587),
            'mail.mailers.smtp.username' => $settings['username'] ?: null,
            'mail.mailers.smtp.password' => self::password(),
            // 'ninguno' se guarda como cadena vacia; Laravel espera null.
            'mail.mailers.smtp.encryption' => $settings['encryption'] ?: null,
        ]);

        if (filled($settings['from_address'])) {
            config(['mail.from.address' => $settings['from_address']]);
        }

        if (filled($settings['from_name'])) {
            config(['mail.from.name' => $settings['from_name']]);
        }
    }

    /** Contrasenia descifrada, o null si no hay o si no se puede descifrar. */
    private static function password(): ?string
    {
        $stored = self::stored()[self::PREFIX.self::PASSWORD] ?? null;

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
