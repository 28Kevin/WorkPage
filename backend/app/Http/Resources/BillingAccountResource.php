<?php

namespace App\Http\Resources;

use App\Models\BillingAccount;
use App\Support\MoneyInWords;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillingAccount */
class BillingAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => BillingAccount::TYPE,
            'document_type_label' => BillingAccount::TYPE_LABEL,
            'number' => $this->number,
            'number_code' => $this->number_code,

            'issue_place' => $this->issue_place,
            'issue_date' => $this->issue_date->toDateString(),

            'company' => [
                'id' => $this->billing_company_id,
                'name' => $this->company_name,
                'nit' => $this->company_nit,
                'address' => $this->company_address,
                'city' => $this->company_city,
            ],

            'items' => array_map(fn (array $item) => [
                ...$item,
                'unit_price_label' => MoneyInWords::format((int) $item['unit_price']),
                'subtotal_label' => MoneyInWords::format((int) $item['subtotal']),
            ], $this->items ?? []),

            'total' => $this->total,
            // El documento imprime el total en numeros y en letras.
            'total_label' => MoneyInWords::format($this->total),
            'total_in_words' => MoneyInWords::spell($this->total),

            'annulment' => [
                'annulled' => $this->isAnnulled(),
                'annulled_at' => $this->annulled_at?->toIso8601String(),
                'reason' => $this->annulment_reason,
            ],

            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'pdf_url' => route('api.billing.pdf', $this->id),
        ];
    }
}
