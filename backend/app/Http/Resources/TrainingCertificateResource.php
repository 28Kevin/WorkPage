<?php

namespace App\Http\Resources;

use App\Models\TrainingCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainingCertificate */
class TrainingCertificateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => TrainingCertificate::TYPE,
            'document_type_label' => TrainingCertificate::TYPE_LABEL,
            'order_number' => $this->order_number,
            'order_code' => $this->order_code,

            'participant_name' => $this->participant_name,
            'document_number' => $this->document_number,
            'course' => $this->course,
            'city' => $this->city,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'hours' => $this->hours,
            'signed_date' => $this->signed_date->toDateString(),

            // No salen en el PDF: son para la pagina publica del QR.
            'company' => [
                'nit' => $this->company_nit,
                'logo' => $this->company_logo,
            ],

            'issued_at' => $this->issued_at?->toIso8601String(),

            'verification' => [
                'code' => $this->verification_code,
                'url' => $this->verificationUrl(),
            ],

            'annulment' => [
                'annulled' => $this->isAnnulled(),
                'annulled_at' => $this->annulled_at?->toIso8601String(),
                'reason' => $this->annulment_reason,
            ],

            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'pdf_url' => route('api.certificates.pdf', $this->id),
        ];
    }
}
