<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Interruptor general de la plataforma. Apagada, la API solo atiende el
 * ingreso y a las cuentas de desarrollo, que son las que pueden encenderla.
 */
class SystemSwitch
{
    public const KEY = 'system.enabled';

    public static function enabled(): bool
    {
        // Sin el ajuste guardado la plataforma se considera encendida.
        return (Setting::map()[self::KEY] ?? '1') === '1';
    }

    public static function set(bool $enabled): void
    {
        Setting::putMany([self::KEY => $enabled ? '1' : '0']);
    }
}
