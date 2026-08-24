<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MedicalExam */
class MedicalExamResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_code' => $this->order_code,
            'worker' => [
                'full_name' => $this->full_name,
                'document_number' => $this->document_number,
                'birth_date' => $this->birth_date->toDateString(),
                'age' => $this->age,
                'email' => $this->email,
                'phone' => $this->phone,
                'height_cm' => $this->height_cm,
                'ideal_weight_kg' => (float) $this->ideal_weight_kg,
                'weight_kg' => (float) $this->weight_kg,
            ],
            'occupational' => [
                'company_name' => $this->company_name,
                'company_nit' => $this->company_nit,
                'position' => $this->position,
                'eps' => $this->whenLoaded('eps', fn () => [
                    'id' => $this->eps->id,
                    'name' => $this->eps->name,
                ]),
                'arl' => $this->whenLoaded('arl', fn () => [
                    'id' => $this->arl->id,
                    'name' => $this->arl->name,
                    'certificate_url' => $this->arl->certificate_url,
                ]),
                'city' => $this->whenLoaded('city', fn () => [
                    'id' => $this->city->id,
                    'name' => $this->city->name,
                    'department' => $this->city->department,
                ]),
                'risks' => $this->whenLoaded('risks', fn () => $this->risks->map(fn ($risk) => [
                    'id' => $risk->id,
                    'name' => $risk->name,
                    'slug' => $risk->slug,
                ])->all()),
            ],
            'exam' => [
                'exam_date' => $this->exam_date->toDateString(),
                'exam_type' => $this->exam_type->value,
                'exam_type_label' => $this->exam_type->label(),
                'result' => $this->result->value,
                'result_label' => $this->result->label(),
                'recommendations' => array_values(array_filter(explode("\n", (string) $this->recommendations))),
            ],
            'medical_parameters' => $this->medical_parameters,
            'verification' => [
                'code' => $this->verification_code,
                'url' => $this->verificationUrl(),
            ],
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'pdf_url' => route('api.exams.pdf', $this->id),
        ];
    }
}
