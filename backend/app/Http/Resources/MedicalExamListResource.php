<?php

namespace App\Http\Resources;

use App\Models\MedicalExam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MedicalExam */
class MedicalExamListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'full_name' => $this->full_name,
            'document_number' => $this->document_number,
            'company_name' => $this->company_name,
            'position' => $this->position,
            'exam_date' => $this->exam_date->toDateString(),
            'exam_type_label' => $this->exam_type->label(),
            'result' => $this->result->value,
            'result_label' => $this->result->shortLabel(),
            'city' => $this->whenLoaded('city', fn () => $this->city?->name),
            'annulled' => $this->annulled_at !== null,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'pdf_url' => route('api.exams.pdf', $this->id),
        ];
    }
}
