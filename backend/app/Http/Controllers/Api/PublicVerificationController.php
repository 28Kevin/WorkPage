<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodHandlerRecord;
use App\Models\MedicalExam;
use App\Models\TrainingCertificate;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Modulo de consulta publica (acceso externo). Solo expone la informacion
 * minima necesaria para validar la existencia y vigencia del examen.
 */
class PublicVerificationController extends Controller
{
    /** Busqueda por numero de cedula. */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
        ], [], ['document_number' => 'número de cédula']);

        $document = $validated['document_number'];

        $exams = MedicalExam::query()
            ->active()
            ->where('document_number', $document)
            ->get()
            ->map(fn (MedicalExam $exam) => $this->examEntry($exam));

        $records = FoodHandlerRecord::query()
            ->active()
            ->where('document_number', $document)
            ->get()
            ->map(fn (FoodHandlerRecord $record) => $this->recordEntry($record));

        // Los dos tipos se mezclan y se ordenan juntos: el consultante ve la
        // historia completa, del documento mas reciente al mas antiguo.
        $results = $exams->concat($records)
            ->sortByDesc(fn (array $entry) => [$entry['exam_date'], $entry['issued_at']])
            ->values();

        if ($results->isEmpty()) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontraron documentos vigentes asociados a la cédula consultada.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'message' => 'Se confirma la emisión de los documentos relacionados a continuación.',
            'issuer' => $this->center()['name'],
            'total' => $results->count(),
            'results' => $results->all(),
        ]);
    }

    /** Destino del codigo QR impreso en el documento. */
    public function verify(string $code): JsonResponse
    {
        $exam = MedicalExam::query()
            ->with('arl')
            ->where('verification_code', $code)
            ->first();

        if (! $exam) {
            $record = FoodHandlerRecord::where('verification_code', $code)->first();

            if ($record) {
                return $this->verifyRecord($record);
            }
        }

        if (! $exam) {
            return response()->json([
                'valid' => false,
                'message' => 'El código de verificación no corresponde a ningún documento emitido por este centro médico.',
            ], 404);
        }

        $center = $this->center();

        // Un documento anulado sigue respondiendo: el QR ya esta impreso.
        if ($exam->isAnnulled()) {
            return response()->json([
                'valid' => false,
                'annulled' => true,
                'message' => sprintf(
                    'El documento No. %s fue anulado el %s por %s y no tiene validez.',
                    $exam->order_code,
                    $exam->annulled_at->translatedFormat('d \d\e F \d\e Y'),
                    $center['name'],
                ),
            ], 410);
        }

        return response()->json([
            'valid' => true,
            'legend' => sprintf(
                'Documento verificado. El examen médico ocupacional No. %s fue emitido por %s (NIT %s) el %s. Este certificado es auténtico y se encuentra registrado en nuestra plataforma.',
                $exam->order_code,
                $center['name'],
                $center['nit'],
                $exam->issued_at->translatedFormat('d \d\e F \d\e Y'),
            ),
            'issuer' => [
                'name' => $center['name'],
                'nit' => $center['nit'],
                'phone' => $center['phone'],
                'email' => $center['email'],
            ],
            'exam' => [
                'order_code' => $exam->order_code,
                'full_name' => $this->maskName($exam->full_name),
                'document_number' => $this->maskDocument($exam->document_number),
                'exam_type_label' => $exam->exam_type->label(),
                'result_label' => $exam->result->label(),
                'exam_date' => $exam->exam_date->toDateString(),
                'issued_at' => $exam->issued_at->toIso8601String(),
                'issued_at_label' => $exam->issued_at->translatedFormat('d \d\e F \d\e Y, h:i a'),
                'arl' => $exam->arl?->name,
            ],
        ]);
    }

    /**
     * Entrada del examen ocupacional en la busqueda publica. Conserva las
     * claves que ya consumia la pagina; 'document_type' se agrega para que el
     * consultante sepa cual de los dos documentos esta viendo.
     *
     * @return array<string, mixed>
     */
    private function examEntry(MedicalExam $exam): array
    {
        return [
            'document_type' => 'medical_exam',
            'document_type_label' => 'Examen médico ocupacional',
            'order_code' => $exam->order_code,
            'full_name' => $this->maskName($exam->full_name),
            'document_number' => $this->maskDocument($exam->document_number),
            'exam_type_label' => $exam->exam_type->label(),
            'result_label' => $exam->result->label(),
            'exam_date' => $exam->exam_date->toDateString(),
            'issued_at' => $exam->issued_at->toIso8601String(),
            'issued_at_label' => $exam->issued_at->translatedFormat('d \d\e F \d\e Y, h:i a'),
            'verification_code' => $exam->verification_code,
        ];
    }

    /**
     * Entrada del paquete de alimentos. Reutiliza los nombres de clave que
     * significan lo mismo para que la pagina recorra una sola lista.
     *
     * @return array<string, mixed>
     */
    private function recordEntry(FoodHandlerRecord $record): array
    {
        return [
            'document_type' => FoodHandlerRecord::TYPE,
            'document_type_label' => FoodHandlerRecord::TYPE_LABEL,
            'order_code' => $record->order_code,
            'full_name' => $this->maskName($record->full_name),
            'document_number' => $this->maskDocument($record->document_number),
            'exam_date' => $record->issue_date->toDateString(),
            'issued_at' => $record->issued_at->toIso8601String(),
            'issued_at_label' => $record->issued_at->translatedFormat('d \d\e F \d\e Y, h:i a'),
            'verification_code' => $record->verification_code,
        ];
    }

    /** Respuesta del QR del paquete de alimentos. */
    private function verifyRecord(FoodHandlerRecord $record): JsonResponse
    {
        $center = $this->center();

        if ($record->isAnnulled()) {
            return response()->json([
                'valid' => false,
                'annulled' => true,
                'document_type' => FoodHandlerRecord::TYPE,
                'document_type_label' => FoodHandlerRecord::TYPE_LABEL,
                'message' => sprintf(
                    'El documento No. %s fue anulado el %s por %s y no tiene validez.',
                    $record->order_code,
                    $record->annulled_at->translatedFormat('d \d\e F \d\e Y'),
                    $center['name'],
                ),
            ], 410);
        }

        return response()->json([
            'valid' => true,
            'document_type' => FoodHandlerRecord::TYPE,
            'document_type_label' => FoodHandlerRecord::TYPE_LABEL,
            'legend' => sprintf(
                '%s No. %s emitido por %s (NIT %s) el %s. Este documento es auténtico y se encuentra registrado en nuestra plataforma.',
                FoodHandlerRecord::TYPE_LABEL,
                $record->order_code,
                $center['name'],
                $center['nit'],
                $record->issued_at->translatedFormat('d \d\e F \d\e Y'),
            ),
            'issuer' => [
                'name' => $center['name'],
                'nit' => $center['nit'],
                'phone' => $center['phone'],
                'email' => $center['email'],
            ],
            'exam' => [
                'order_code' => $record->order_code,
                'full_name' => $this->maskName($record->full_name),
                'document_number' => $this->maskDocument($record->document_number),
                'exam_date' => $record->issue_date->toDateString(),
                'issued_at' => $record->issued_at->toIso8601String(),
                'issued_at_label' => $record->issued_at->translatedFormat('d \d\e F \d\e Y, h:i a'),
            ],
        ]);
    }

    /**
     * Destino del QR del certificado de capacitacion. Responde en su propio
     * endpoint porque la pagina muestra el logo y el NIT de la empresa que
     * respalda la formacion, que el examen medico no tiene.
     */
    public function certificate(string $code): JsonResponse
    {
        $certificate = TrainingCertificate::where('verification_code', $code)->first();

        if (! $certificate) {
            return response()->json([
                'valid' => false,
                'message' => 'El código de verificación no corresponde a ningún certificado emitido.',
            ], 404);
        }

        $center = $this->center();

        // Anulado: el QR ya esta impreso, asi que responde igual, sin validez.
        if ($certificate->isAnnulled()) {
            return response()->json([
                'valid' => false,
                'annulled' => true,
                'document_type' => TrainingCertificate::TYPE,
                'document_type_label' => TrainingCertificate::TYPE_LABEL,
                'message' => sprintf(
                    'El certificado No. %s fue anulado el %s y no tiene validez.',
                    $certificate->order_code,
                    $certificate->annulled_at->translatedFormat('d \d\e F \d\e Y'),
                ),
            ], 410);
        }

        return response()->json([
            'valid' => true,
            'document_type' => TrainingCertificate::TYPE,
            'document_type_label' => TrainingCertificate::TYPE_LABEL,
            'legend' => sprintf(
                'Certificación verificada. %s, identificado(a) con documento %s, cursó y aprobó la formación en %s, con una intensidad de %d horas, finalizada el %s.',
                $certificate->participant_name,
                $this->maskDocument($certificate->document_number),
                $certificate->course,
                $certificate->hours,
                $certificate->end_date->translatedFormat('d \d\e F \d\e Y'),
            ),
            'certificate' => [
                'order_code' => $certificate->order_code,
                'participant_name' => $this->maskName($certificate->participant_name),
                'document_number' => $this->maskDocument($certificate->document_number),
                'course' => $certificate->course,
                'city' => $certificate->city,
                'hours' => $certificate->hours,
                'start_date' => $certificate->start_date->toDateString(),
                'end_date' => $certificate->end_date->toDateString(),
                'issued_at' => $certificate->issued_at->toIso8601String(),
                'issued_at_label' => $certificate->issued_at->translatedFormat('d \d\e F \d\e Y, h:i a'),
            ],
            // Lo que el formulario carga para esta pagina.
            'company' => [
                'nit' => $certificate->company_nit,
                'logo' => $certificate->company_logo,
            ],
            'issuer' => [
                'name' => $center['name'],
                'nit' => $center['nit'],
                'phone' => $center['phone'],
                'email' => $center['email'],
            ],
        ]);
    }

    /** Datos del centro tal como quedaron en el modulo de configuracion. */
    private function center(): array
    {
        return Branding::all()['center'];
    }

    /** Muestra solo iniciales y primer apellido para proteger datos personales. */
    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return collect($parts)
            ->map(fn (string $part, int $i) => $i === 0 ? $part : Str::upper(Str::substr($part, 0, 1)).'.')
            ->implode(' ');
    }

    private function maskDocument(string $document): string
    {
        $visible = Str::substr($document, -4);

        return str_repeat('*', max(0, Str::length($document) - 4)).$visible;
    }
}
