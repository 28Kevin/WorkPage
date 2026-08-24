<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalExam;
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

        $exams = MedicalExam::query()
            ->where('document_number', $validated['document_number'])
            ->latest('exam_date')
            ->latest('order_number')
            ->get();

        if ($exams->isEmpty()) {
            return response()->json([
                'found' => false,
                'message' => 'No se encontraron exámenes médicos ocupacionales asociados a la cédula consultada.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'message' => 'Se confirma la realización exitosa del examen médico ocupacional.',
            'issuer' => config('medical_center.name'),
            'total' => $exams->count(),
            'results' => $exams->map(fn (MedicalExam $exam) => [
                'order_code' => $exam->order_code,
                'full_name' => $this->maskName($exam->full_name),
                'document_number' => $this->maskDocument($exam->document_number),
                'exam_type_label' => $exam->exam_type->label(),
                'result_label' => $exam->result->label(),
                'exam_date' => $exam->exam_date->toDateString(),
                'issued_at' => $exam->issued_at->toIso8601String(),
                'issued_at_label' => $exam->issued_at->translatedFormat('d \d\e F \d\e Y, h:i a'),
                'verification_code' => $exam->verification_code,
            ])->all(),
        ]);
    }

    /** Destino del codigo QR impreso en el documento. */
    public function verify(string $code): JsonResponse
    {
        $exam = MedicalExam::query()
            ->with(['city', 'arl'])
            ->where('verification_code', $code)
            ->first();

        if (! $exam) {
            return response()->json([
                'valid' => false,
                'message' => 'El código de verificación no corresponde a ningún documento emitido por este centro médico.',
            ], 404);
        }

        $center = config('medical_center');

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
                'license' => $center['license'],
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
                'city' => $exam->city->name,
                'arl' => $exam->arl->name,
            ],
        ]);
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
