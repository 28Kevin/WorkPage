<?php

namespace App\Services;

use App\Models\MedicalExam;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;

class ExamPdfGenerator
{
    public function __construct(private readonly QrCodeGenerator $qr) {}

    public function make(MedicalExam $exam): PdfWrapper
    {
        $exam->loadMissing(['eps', 'arl', 'city', 'risks']);

        return Pdf::loadView('pdf.medical-exam', [
            'exam' => $exam,
            'parameters' => $exam->medical_parameters,
            'qr' => $this->qr->dataUri($exam->verificationUrl()),
            'verificationUrl' => $exam->verificationUrl(),
            'center' => config('medical_center'),
        ])->setPaper('letter');
    }

    public function filename(MedicalExam $exam): string
    {
        return 'examen-medico-'.$exam->order_code.'-'.$exam->document_number.'.pdf';
    }
}
