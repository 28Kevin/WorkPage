<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Une los ajustes guardados en base de datos con los valores por defecto del
 * .env. Si la tabla esta vacia la aplicacion sigue funcionando igual.
 */
class Branding
{
    /** Luminosidad de cada tono, al estilo de las paletas de Tailwind. */
    private const SHADES = [
        50 => 0.97, 100 => 0.94, 200 => 0.86, 300 => 0.77, 400 => 0.66,
        500 => 0.55, 600 => 0.47, 700 => 0.39, 800 => 0.32, 900 => 0.26,
    ];

    /** Claves aceptadas por grupo. Cualquier otra se descarta al guardar. */
    public const FIELDS = [
        'identity' => ['app_name', 'tagline', 'logo'],
        'theme' => ['brand_color', 'accent_color', 'font_heading', 'font_body', 'radius'],
        'center' => [
            'name', 'nit', 'address', 'phone', 'email',
            'physician_name', 'schedule',
        ],
    ];

    public static function defaults(): array
    {
        return [
            'identity' => [
                'app_name' => config('medical_center.name'),
                'tagline' => 'Exámenes médicos ocupacionales',
                'logo' => null,
            ],
            'theme' => [
                'brand_color' => '#2563eb',
                'accent_color' => '#0284c7',
                'font_heading' => 'Archivo',
                'font_body' => 'Source Sans 3',
                'radius' => '0.75rem',
            ],
            'center' => [
                'name' => config('medical_center.name'),
                'nit' => config('medical_center.nit'),
                'address' => config('medical_center.address'),
                'phone' => config('medical_center.phone'),
                'email' => config('medical_center.email'),
                'physician_name' => config('medical_center.physician.name'),
                'schedule' => "Lunes a viernes: 6:30 a. m. – 3:00 p. m.\nSábados: 8:00 a. m. – 3:00 p. m.",
            ],
        ];
    }

    /** Ajustes efectivos, ya mezclados con los defaults. */
    public static function all(): array
    {
        $stored = Setting::map();
        $result = [];

        foreach (self::defaults() as $group => $fields) {
            foreach ($fields as $key => $default) {
                $value = $stored[$group.'.'.$key] ?? null;
                $result[$group][$key] = ($value === null || $value === '') ? $default : $value;
            }
        }

        $result['theme']['palette'] = self::palette($result['theme']['brand_color']);
        $result['theme']['accent_palette'] = self::palette($result['theme']['accent_color']);

        return $result;
    }

    /** Aplana un payload agrupado a las claves "grupo.campo" que se persisten. */
    public static function flatten(array $grouped): array
    {
        $flat = [];

        foreach (self::FIELDS as $group => $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $grouped[$group] ?? [])) {
                    $flat[$group.'.'.$key] = $grouped[$group][$key];
                }
            }
        }

        return $flat;
    }

    /** Deriva los diez tonos de una paleta a partir de un color base. */
    public static function palette(string $hex): array
    {
        [$h, $s, $l] = self::hexToHsl($hex);

        $palette = [];

        foreach (self::SHADES as $shade => $lightness) {
            // Los tonos muy claros se desaturan un poco para que no queden fosforescentes.
            $saturation = $lightness > 0.85 ? $s * 0.85 : $s;
            $palette[$shade] = self::hslToHex($h, $saturation, $lightness);
        }

        return $palette;
    }

    private static function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0.0) {
            return [0.0, 0.0, $l];
        }

        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };

        return [$h / 6, $s, $l];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        if ($s == 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - ($l * $s);
            $p = (2 * $l) - $q;

            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        return match (true) {
            $t < 1 / 6 => $p + (($q - $p) * 6 * $t),
            $t < 1 / 2 => $q,
            $t < 2 / 3 => $p + (($q - $p) * (2 / 3 - $t) * 6),
            default => $p,
        };
    }
}
