<?php

namespace App\Http\Resources;

use App\Models\FoodHandlerRecord;
use App\Support\FoodHandlerForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FoodHandlerRecord */
class FoodHandlerRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => FoodHandlerRecord::TYPE,
            'document_type_label' => FoodHandlerRecord::TYPE_LABEL,
            'order_number' => $this->order_number,
            'order_code' => $this->order_code,

            'patient' => [
                'full_name' => $this->full_name,
                'document_number' => $this->document_number,
                'age' => $this->age,
                'photo' => $this->photo,
            ],

            'issue_date' => $this->issue_date->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),

            // Panel > grupo > campo, con la etiqueta de cada uno para que la
            // vista no tenga que repetir el catalogo.
            'lab_results' => $this->labResults(),

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
            'pdf_url' => route('api.food-handler.pdf', $this->id),
        ];
    }

    /**
     * Resultados con etiqueta, en el orden del formato.
     *
     * @return array<int, array<string, mixed>>
     */
    private function labResults(): array
    {
        $stored = $this->lab_results ?? [];

        return array_map(fn (array $panel) => [
            ...$panel,
            'groups' => array_map(fn (array $group) => [
                ...$group,
                'fields' => array_map(fn (array $field) => [
                    ...$field,
                    'value' => $stored[$panel['key']][$group['key']][$field['key']] ?? $field['normal'],
                ], $group['fields']),
            ], $panel['groups']),
        ], FoodHandlerForm::catalog());
    }
}
