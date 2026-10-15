<?php

namespace App\Providers;

use App\Support\MailSettings;
use Illuminate\Support\ServiceProvider;

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
    }
}
