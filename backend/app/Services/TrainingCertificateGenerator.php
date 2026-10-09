<?php

namespace App\Services;

use App\Models\TrainingCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea y corrige certificados de capacitacion. El consecutivo sigue el formato
 * del documento de guia ("S.S-1370"): prefijo y numero, sin anio ni relleno.
 */
class TrainingCertificateGenerator
{
    private const RELATIONS = ['creator'];

    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly VerificationCodeGenerator $verificationCodes,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null): TrainingCertificate
    {
        return DB::transaction(function () use ($data, $user) {
            $number = $this->orderNumbers->next(OrderNumberGenerator::TRAINING_KEY);

            $certificate = TrainingCertificate::create([
                ...$this->attributes($data),
                'order_number' => $number,
                'order_code' => self::formatCode($number),
                'verification_code' => $this->verificationCodes->generate(),
                'issued_at' => now(),
                'created_by' => $user?->id,
            ]);

            return $certificate->load(self::RELATIONS);
        });
    }

    /**
     * Corrige conservando consecutivo, codigo de verificacion y fecha de
     * expedicion: el QR ya impreso debe seguir sirviendo.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TrainingCertificate $certificate, array $data): TrainingCertificate
    {
        return DB::transaction(function () use ($certificate, $data) {
            $certificate->update($this->attributes($data));

            return $certificate->fresh(self::RELATIONS);
        });
    }

    public static function formatCode(int $number): string
    {
        return OrderNumberGenerator::TRAINING_PREFIX.'-'.$number;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'participant_name' => $data['participant_name'],
            'document_number' => $data['document_number'],
            'course' => $data['course'],
            'city' => $data['city'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'hours' => (int) $data['hours'],
            'signed_date' => $data['signed_date'],
            // Solo se muestran en la pagina publica, no en el PDF.
            'company_nit' => $data['company_nit'] ?? null,
            'company_logo' => $data['company_logo'] ?? null,
        ];
    }
}
