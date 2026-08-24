<?php

namespace App\Services;

use App\Models\MedicalExam;
use App\Support\Branding;
use App\Support\ExamForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;

class ExamPdfGenerator
{
    public function __construct(private readonly QrCodeGenerator $qr) {}

    public function make(MedicalExam $exam): PdfWrapper
    {
        $exam->loadMissing(['eps', 'arl', 'afp', 'city', 'risks']);

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
        ])->setPaper('letter');
    }

    public function filename(MedicalExam $exam): string
    {
        return 'examen-medico-'.$exam->order_code.'-'.$exam->document_number.'.pdf';
    }

    /** Conserva la forma anidada que ya espera la plantilla. */
    private function center(array $center): array
    {
        return [
            ...$center,
            'physician' => [
                'name' => $center['physician_name'],
                'license' => $center['physician_license'],
            ],
        ];
    }
}
