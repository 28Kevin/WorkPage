<?php

namespace App\Providers;

use App\Support\MailSettings;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Las credenciales de correo configuradas en el panel pisan a las del
         * .env. Se aplican aqui para que el envio y la comprobacion previa
         * miren lo mismo; MailSettings ya tolera que la tabla no exista todavia
         * durante las migraciones iniciales.
         */
        MailSettings::apply();

        /*
         * La sesion se cierra tras unos minutos sin actividad: el token vale
         * mientras su ultimo uso (o su creacion) sea reciente. Sanctum actualiza
         * last_used_at en cada peticion, asi que usar la plataforma la mantiene viva.
         */
        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid) {
            $lastActivity = $token->last_used_at ?? $token->created_at;

            return $isValid && $lastActivity->gt(now()->subMinutes(config('sanctum.idle_minutes')));
        });
    }
}
