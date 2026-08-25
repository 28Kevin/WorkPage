<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Imagenes fijas del certificado: la marca de agua y el sello con la firma del
 * medico. Llegan como JPEG, que no tiene canal alfa, asi que el blanco de fondo
 * se vuelve transparente antes de incrustarlas; si no, taparian el texto.
 *
 * El resultado se cachea porque la conversion recorre pixel a pixel.
 */
class PdfAssets
{
    /**
     * Por encima de esta luminancia el pixel se considera fondo. No conviene
     * subirlo: el ruido casi-blanco del JPEG se volveria un recuadro gris.
     */
    private const WHITE_THRESHOLD = 242;

    /** Cuanto se atenua cada imagen. */
    private const OPACITY = [
        'logo1.jpeg' => 1.0,
        'sello.jpeg' => 1.0,
    ];

    /**
     * Curva aplicada a la opacidad de cada pixel. El logo de origen es muy
     * palido: por debajo de 1 se realza su tinta clara para que la marca de
     * agua se perciba sin llegar a estorbar la lectura.
     */
    private const GAMMA = [
        'logo1.jpeg' => 0.35,
        'sello.jpeg' => 1.0,
    ];

    /**
     * Ancho maximo en pixeles. El canal alfa encarece mucho el PNG dentro del
     * PDF, y la marca se imprime a unos 3 cm: no necesita mas resolucion.
     */
    private const MAX_WIDTH = [
        'logo1.jpeg' => 480,
        'sello.jpeg' => 236,
    ];

    /** Marca de agua repetida en todas las paginas. */
    public static function watermark(): ?string
    {
        return self::dataUri('logo1.jpeg');
    }

    /** Sello y firma del medico ocupacional. */
    public static function signature(): ?string
    {
        return self::dataUri('sello.jpeg');
    }

    /** Devuelve el PNG con fondo transparente como data URI, o null si falta. */
    private static function dataUri(string $file): ?string
    {
        $path = base_path($file);

        if (! is_readable($path)) {
            return null;
        }

        $opacity = self::OPACITY[$file] ?? 1.0;
        $gamma = self::GAMMA[$file] ?? 1.0;
        $maxWidth = self::MAX_WIDTH[$file] ?? 480;

        // La clave incluye los parametros: al ajustarlos hay que reconvertir,
        // aunque el archivo de origen siga siendo el mismo.
        $key = sprintf(
            'pdf.asset.%s.%d.%s',
            $file,
            filemtime($path),
            md5($opacity.'|'.$gamma.'|'.$maxWidth.'|'.self::WHITE_THRESHOLD),
        );

        return Cache::rememberForever(
            $key,
            fn () => self::transparentPng($path, $opacity, $gamma, $maxWidth),
        );
    }

    /**
     * Recorta el fondo blanco y atenua el resto. La opacidad se aplica aqui y
     * no con CSS porque dompdf no siempre respeta opacity sobre imagenes.
     */
    private static function transparentPng(string $path, float $opacity, float $gamma, int $maxWidth): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $source = @imagecreatefromjpeg($path);

        if (! $source) {
            return null;
        }

        if (imagesx($source) > $maxWidth) {
            $source = imagescale($source, $maxWidth);
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagefill($output, 0, 0, imagecolorallocatealpha($output, 255, 255, 255, 127));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($source, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

                if ($luminance >= self::WHITE_THRESHOLD) {
                    continue;
                }

                // Cuanto mas oscuro el pixel, mas opaco queda (0 = opaco, 127 = invisible).
                $strength = ((1 - ($luminance / self::WHITE_THRESHOLD)) ** $gamma) * $opacity;
                $alpha = (int) round(127 - (127 * $strength));

                // Se compone el color a mano: allocate por pixel seria muy lento.
                imagesetpixel($output, $x, $y, ($alpha << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }

        ob_start();
        imagepng($output, null, 9);
        $png = ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
