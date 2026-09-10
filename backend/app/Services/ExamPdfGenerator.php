<?php

namespace App\Services;

use App\Models\MedicalExam;
use App\Support\Branding;
use App\Support\ExamForm;
use App\Support\PdfAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Str;

class ExamPdfGenerator
{
    public function __construct(private readonly QrCodeGenerator $qr) {}

    public function make(MedicalExam $exam): PdfWrapper
    {
        $exam->loadMissing(['eps', 'arl', 'risks']);

        $branding = Branding::all();

        return Pdf::loadView('pdf.medical-exam', [
            'exam' => $exam,
            'parameters' => $exam->medical_parameters ?? [],
            'paraclinicals' => $exam->paraclinicals ?? [],

            // Etiquetas del formato: viven en un solo sitio para no repetirlas.
            'systems' => ExamForm::SYSTEMS,
            'paraclinicalLabels' => ExamForm::PARACLINICALS,
            'assessmentLabels' => ExamForm::ASSESSMENTS,
            'aptitudeLabels' => ExamForm::APTITUDES,

            'qr' => $this->qr->dataUri($exam->verificationUrl()),
            'verificationUrl' => $exam->verificationUrl(),
            'center' => $this->center($branding['center']),
            'palette' => $branding['theme']['palette'],
            'logo' => $branding['identity']['logo'],
            'photo' => $exam->photo,

            // Marca de agua de todas las paginas y sello del medico.
            'watermark' => PdfAssets::watermark(),
            'signature' => PdfAssets::signature(),
        ])->setPaper('letter');
    }

    public function filename(MedicalExam $exam): string
    {
        $name = $this->sanitizeForFilename((string) $exam->full_name);

        return trim($name.' - '.$exam->order_code, ' -').'.pdf';
    }

    /**
     * El nombre viaja en la cabecera Content-Disposition y termina siendo un
     * archivo en el disco de quien descarga: fuera separadores de ruta, los
     * caracteres que Windows no admite y los espacios de mas.
     */
    private function sanitizeForFilename(string $value): string
    {
        // Separadores de ruta y caracteres que Windows rechaza en un nombre.
        $clean = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $value);
        $clean = preg_replace('~[\x00-\x1F]+~u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('~\s+~u', ' ', $clean) ?? '');

        return Str::limit($clean, 80, '');
    }

    /** Conserva la forma anidada que ya espera la plantilla. */
    private function center(array $center): array
    {
        return [
            ...$center,
            'physician' => ['name' => $center['physician_name']],
        ];
    }
}
