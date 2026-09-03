<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Respaldo de la base
|--------------------------------------------------------------------------
|
| Estas tareas solo se ejecutan si algo llama a `php artisan schedule:run`
| cada minuto. En el plan gratuito de Render no hay cron y la instancia se
| suspende, asi que ahi el respaldo se dispara a mano desde Configuracion.
| Al agregar un Cron Job o pasar a un plan de pago, empiezan a correr solas.
|
*/
Schedule::command('respaldo:enviar')
    ->weeklyOn(1, '03:00')
    ->timezone(config('app.timezone'));

// Los respaldos viejos se borran segun la politica de config/backup.php.
Schedule::command('backup:clean')
    ->daily()
    ->at('04:00')
    ->timezone(config('app.timezone'));
