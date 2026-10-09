<?php

namespace App\Http\Resources;

use App\Models\TrainingCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainingCertificate */
class TrainingCertificateListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'participant_name' => $this->participant_name,
            'document_number' => $this->document_number,
            'course' => $this->course,
            'end_date' => $this->end_date->toDateString(),
            'hours' => $this->hours,
            'annulled' => $this->annulled_at !== null,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'pdf_url' => route('api.certificates.pdf', $this->id),
        ];
    }
}
