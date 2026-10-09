<?php

namespace App\Services;

use App\Models\FoodHandlerRecord;
use App\Support\Branding;
use App\Support\FoodHandlerForm;
use App\Support\PdfAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Str;

/**
 * Paquete de manipulacion de alimentos en PDF: cinco paginas tamanio carta mas
 * el carnet.
 *
 * Las imagenes fijas de la plantilla (logos, pies de pagina, firmas y la
 * tarjeta de la asesora) se buscan por nombre de archivo en public/images. Las
 * que falten no rompen el documento: esa firma sale como linea en blanco.
 */
class FoodHandlerPdfGenerator
{
    /** Nombre de archivo de cada imagen fija, en public/images. */
    private const TEMPLATE_IMAGES = [
        'header' => 'alimentos-encabezado.png',
        'footer' => 'alimentos-pie.png',
        'bacteriologist' => 'firma-bacteriologa.png',
        'physician' => 'firma-medico.png',
        'food_engineer' => 'firma-ingeniera-alimentos.png',
        'advisor_card' => 'tarjeta-asesora.png',
    ];

    public function __construct(private readonly QrCodeGenerator $qr) {}

    public function make(FoodHandlerRecord $record): PdfWrapper
    {
        $branding = Branding::all();

        return Pdf::loadView('pdf.food-handler', [
            'record' => $record,
            'results' => $record->lab_results ?? FoodHandlerForm::normalResults(),
            'panels' => FoodHandlerForm::PANELS,
            'signatureLabels' => FoodHandlerForm::SIGNATURES,

            'center' => $branding['center'],
            'texts' => $branding['food_handler'],
            'palette' => $branding['theme']['palette'],

            'qr' => $this->qr->dataUri($record->verificationUrl()),
            'verificationUrl' => $record->verificationUrl(),

            'images' => $this->templateImages(),
            'watermark' => PdfAssets::watermark(),
        ])->setPaper('letter');
    }

    public function filename(FoodHandlerRecord $record): string
    {
        $name = $this->sanitizeForFilename((string) $record->full_name);

        return trim($name.' - '.$record->order_code, ' -').'.pdf';
    }

    /**
     * Imagenes de la plantilla ya como data URI. Las ausentes quedan en null.
     *
     * @return array<string, string|null>
     */
    private function templateImages(): array
    {
        return array_map(
            fn (string $file) => PdfAssets::raw($file),
            self::TEMPLATE_IMAGES,
        );
    }

    /**
     * El nombre viaja en Content-Disposition y termina siendo un archivo en el
     * disco de quien descarga: fuera separadores de ruta y lo que Windows
     * rechaza. Misma regla que el examen ocupacional.
     */
    private function sanitizeForFilename(string $value): string
    {
        $clean = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $value);
        $clean = preg_replace('~[\x00-\x1F]+~u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('~\s+~u', ' ', $clean) ?? '');

        return Str::limit($clean, 80, '');
    }
}
