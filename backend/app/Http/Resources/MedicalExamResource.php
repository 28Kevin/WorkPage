<?php

namespace App\Http\Resources;

use App\Models\MedicalExam;
use App\Support\ExamForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MedicalExam */
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
                'document_type' => $this->document_type?->value,
                'document_type_label' => $this->document_type?->label(),
                'document_number' => $this->document_number,
                'birth_date' => $this->birth_date->toDateString(),
                'age' => $this->age,
                'sex' => $this->sex?->value,
                'sex_label' => $this->sex?->label(),
                'photo' => $this->photo,
                'email' => $this->email,
                'phone' => $this->phone,
                'height_cm' => $this->height_cm,
                'ideal_weight_kg' => (float) $this->ideal_weight_kg,
                'weight_kg' => (float) $this->weight_kg,
            ],
            'occupational' => [
                'is_independent' => (bool) $this->is_independent,
                'company_name' => $this->company_name,
                'company_nit' => $this->company_nit,
                'client_company' => $this->client_company,
                'economic_activity' => $this->economic_activity,
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
                'afp' => $this->whenLoaded('afp', fn () => $this->afp ? [
                    'id' => $this->afp->id,
                    'name' => $this->afp->name,
                ] : null),
                'city' => $this->whenLoaded('city', fn () => $this->city ? [
                    'id' => $this->city->id,
                    'name' => $this->city->name,
                    'department' => $this->city->department,
                ] : null),
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
                'clinical_findings' => $this->clinical_findings,
                'recommendations' => array_values(array_filter(explode("\n", (string) $this->recommendations))),
                'restrictions' => $this->restrictions,
                'restrictions_validity' => $this->restrictions_validity,
                'consent_accepted' => $this->consent_accepted,
            ],
            'aptitudes' => $this->aptitudes(),
            'medical_parameters' => $this->medical_parameters,
            'paraclinicals' => $this->paraclinicalsWithLabels(),
            'verification' => [
                'code' => $this->verification_code,
                'url' => $this->verificationUrl(),
            ],
            'annulment' => [
                'annulled' => $this->isAnnulled(),
                'annulled_at' => $this->annulled_at?->toIso8601String(),
                'reason' => $this->annulment_reason,
            ],
            'issued_at' => $this->issued_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'pdf_url' => route('api.exams.pdf', $this->id),
        ];
    }

    /** Los tres conceptos del formato, ya con su etiqueta legible. */
    private function aptitudes(): array
    {
        $aptitudes = [];

        foreach (ExamForm::APTITUDES as $key => $label) {
            $value = $this->{$key};

            $aptitudes[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value?->value,
                'value_label' => $value?->label(),
            ];
        }

        return $aptitudes;
    }

    /** @return array<int, array<string, mixed>> */
    private function paraclinicalsWithLabels(): array
    {
        $stored = $this->paraclinicals ?? [];
        $paraclinicals = [];

        foreach (ExamForm::PARACLINICALS as $key => $label) {
            $entry = $stored[$key] ?? null;

            if ($entry === null) {
                continue;
            }

            $paraclinicals[] = [
                'key' => $key,
                'label' => $label,
                'performed' => (bool) ($entry['performed'] ?? false),
                'status' => $entry['status'] ?? null,
                'result' => $entry['result'] ?? null,
            ];
        }

        return $paraclinicals;
    }
}
