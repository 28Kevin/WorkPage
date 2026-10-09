<?php

namespace App\Http\Resources;

use App\Models\FoodHandlerRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FoodHandlerRecord */
class FoodHandlerRecordListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'full_name' => $this->full_name,
            'document_number' => $this->document_number,
            'age' => $this->age,
            'issue_date' => $this->issue_date->toDateString(),
            'annulled' => $this->annulled_at !== null,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'pdf_url' => route('api.food-handler.pdf', $this->id),
        ];
    }
}
