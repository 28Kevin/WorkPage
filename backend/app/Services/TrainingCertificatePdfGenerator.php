<?php

namespace App\Services;

use App\Models\TrainingCertificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Str;

/**
 * Certificado de capacitacion. El diseno es la plantilla de guia incrustada
 * como imagen de pagina completa; encima se dibuja el texto variable en las
 * mismas coordenadas del PDF original.
 *
 * Se usa el lienzo de dompdf en vez de una plantilla HTML porque el documento
 * exige posiciones exactas en puntos, y el flujo de CSS no las garantiza.
 *
 * Las coordenadas de LAYOUT salen de descomponer el PDF de guia y van en
 * unidades PDF (origen abajo-izquierda), igual que en el original, para poder
 * compararlas con el a simple vista.
 */
class TrainingCertificatePdfGenerator
{
    /** Carta apaisada, tal como el documento de guia. */
    private const PAGE_WIDTH = 792.0;

    private const PAGE_HEIGHT = 612.0;

    private const BACKGROUND = 'certificado-fondo.jpg';

    /** Colores exactos del original. */
    private const GOLD = [0.83922, 0.71373, 0.07451];

    private const BLUE = [0.24314, 0.34510, 0.76078];

    private const GREY = [0.49804, 0.49804, 0.52549];

    private const BLACK = [0.0, 0.0, 0.0];

    /**
     * Tipografias sustitutas. Las del original (FranklinGothic-DemiCond y
     * FuturaBT-MediumItalic) son comerciales y en el PDF de guia solo vienen
     * como subconjunto, con las letras justas de ese certificado.
     */
    private const TITLE_FONT = ['helvetica', 'bold'];

    private const BODY_FONT = ['helvetica', 'italic'];

    /** QR del original: 80 x 80 puntos en (150, 80). */
    private const QR_X = 150.0;

    private const QR_Y = 80.0;

    private const QR_SIZE = 80.0;

    public function __construct(private readonly QrCodeGenerator $qr) {}

    public function make(TrainingCertificate $certificate): PdfWrapper
    {
        $pdf = Pdf::loadHTML('<html><body></body></html>')->setPaper('letter', 'landscape');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();

        $background = public_path('images/'.self::BACKGROUND);

        if (is_readable($background)) {
            $canvas->image($background, 0, 0, self::PAGE_WIDTH, self::PAGE_HEIGHT);
        }

        foreach ($this->layout($certificate) as $item) {
            $font = $metrics->getFont(...$item['font']);
            $size = $this->fittedSize($metrics, $item, $font);
            $width = $metrics->getTextWidth($item['text'], $font, $size);

            // 'centered' recentra el bloque: el texto cambia de largo con cada
            // participante y el original esta centrado en la pagina.
            $x = $item['centered']
                ? (self::PAGE_WIDTH - $width) / 2
                : $item['x'];

            $canvas->text(
                $x,
                $this->fromBottom($item['y'], $size),
                $item['text'],
                $font,
                $size,
                $item['color'],
            );
        }

        $this->drawQr($canvas, $certificate);

        return $pdf;
    }

    public function filename(TrainingCertificate $certificate): string
    {
        $name = $this->sanitizeForFilename((string) $certificate->participant_name);

        return trim('Certificado '.$name, ' ').'.pdf';
    }

    /**
     * Textos variables del certificado, en coordenadas PDF del original.
     *
     * @return array<int, array<string, mixed>>
     */
    private function layout(TrainingCertificate $certificate): array
    {
        return [
            // Nombre del participante.
            [
                'text' => Str::upper($certificate->participant_name),
                'x' => 115.4, 'y' => 382.0, 'centered' => true, 'max_width' => 620.0,
                'font' => self::TITLE_FONT, 'size' => 38.0, 'color' => self::BLUE,
            ],
            // "Identificado con" y la cedula van en la misma linea del original.
            [
                'text' => 'Identificado con Cédula de ciudadanía Nº '.$certificate->document_number,
                'x' => 200.0, 'y' => 336.0, 'centered' => true, 'max_width' => 620.0,
                'font' => self::BODY_FONT, 'size' => 16.0, 'color' => self::BLACK,
            ],
            // Curso aprobado.
            [
                'text' => Str::upper($certificate->course),
                'x' => 305.1, 'y' => 238.0, 'centered' => true, 'max_width' => 560.0,
                'font' => self::TITLE_FONT, 'size' => 32.0, 'color' => self::GOLD,
            ],
            // Fechas del curso.
            [
                'text' => sprintf(
                    'Realizado en %s del %s al %s',
                    $certificate->city,
                    $this->longDate($certificate->start_date),
                    $this->longDate($certificate->end_date),
                ),
                'x' => 131.23, 'y' => 188.0, 'centered' => true, 'max_width' => 620.0,
                'font' => self::BODY_FONT, 'size' => 12.0, 'color' => self::BLACK,
            ],
            // Intensidad horaria.
            [
                'text' => sprintf('con una intensidad de %d horas.', $certificate->hours),
                'x' => 301.63, 'y' => 176.0, 'centered' => true, 'max_width' => 620.0,
                'font' => self::BODY_FONT, 'size' => 12.0, 'color' => self::BLACK,
            ],
            // Linea de testimonio al pie.
            [
                'text' => sprintf(
                    'En testimonio de lo anterior, se firma en %s a los %s',
                    $certificate->city,
                    $this->longDate($certificate->signed_date),
                ),
                'x' => 211.55, 'y' => 41.0, 'centered' => true, 'max_width' => 620.0,
                'font' => self::BODY_FONT, 'size' => 9.0, 'color' => self::GREY,
            ],
            // Consecutivo, abajo a la derecha. No se centra: el original lo
            // tiene anclado a la derecha junto a su etiqueta.
            [
                'text' => 'CONSECUTIVO:',
                'x' => 562.15, 'y' => 13.0, 'centered' => false,
                'font' => self::BODY_FONT, 'size' => 12.0, 'color' => self::BLUE,
            ],
            [
                'text' => $certificate->order_code,
                'x' => 681.66, 'y' => 13.0, 'centered' => false,
                'font' => self::BODY_FONT, 'size' => 12.0, 'color' => self::BLUE,
            ],
        ];
    }

    /** "(04) del mes de octubre de 2026", como en el documento de guia. */
    private function longDate(\DateTimeInterface $date): string
    {
        return sprintf(
            '(%s) del mes de %s de %s',
            $date->format('d'),
            self::MONTHS[(int) $date->format('n')],
            $date->format('Y'),
        );
    }

    private const MONTHS = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    /**
     * Pasa la linea base del original (medida desde abajo) a la coordenada que
     * espera el lienzo (medida desde arriba).
     *
     * El 0.925 sale de calibrar contra el PDF de guia: dompdf sienta el texto
     * 0.075 x tamanio por encima de donde lo deja la resta simple, y el desvio
     * resulto identico en los cinco tamanios del documento.
     */
    private function fromBottom(float $y, float $size): float
    {
        return self::PAGE_HEIGHT - $y - self::BASELINE_FACTOR * $size;
    }

    private const BASELINE_FACTOR = 0.925;

    /**
     * Reduce el tamanio hasta que el texto quepa en su ancho util.
     *
     * Las tipografias sustitutas son mas anchas que las condensadas del
     * original, asi que un nombre largo se saldria de la pagina. Encoger es
     * preferible a desbordar un documento que se imprime.
     *
     * @param  array<string, mixed>  $item
     */
    private function fittedSize($metrics, array $item, $font): float
    {
        $size = (float) $item['size'];
        $max = $item['max_width'] ?? null;

        if ($max === null) {
            return $size;
        }

        $width = $metrics->getTextWidth($item['text'], $font, $size);

        return $width > $max ? max(6.0, $size * $max / $width) : $size;
    }

    private function drawQr($canvas, TrainingCertificate $certificate): void
    {
        $uri = $this->qr->dataUri($certificate->verificationUrl());

        if (! str_contains($uri, ',')) {
            return;
        }

        // El lienzo necesita un archivo: el QR se escribe en el temporal.
        $png = tempnam(sys_get_temp_dir(), 'qr').'.png';
        file_put_contents($png, base64_decode(explode(',', $uri, 2)[1]));

        $canvas->image(
            $png,
            self::QR_X,
            self::PAGE_HEIGHT - self::QR_Y - self::QR_SIZE,
            self::QR_SIZE,
            self::QR_SIZE,
        );

        @unlink($png);
    }

    private function sanitizeForFilename(string $value): string
    {
        $clean = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $value);
        $clean = preg_replace('~[\x00-\x1F]+~u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('~\s+~u', ' ', $clean) ?? '');

        return Str::limit($clean, 80, '');
    }
}
