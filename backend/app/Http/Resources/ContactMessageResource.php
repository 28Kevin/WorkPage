<?php

namespace App\Http\Resources;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactMessage */
class ContactMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'handled' => $this->isHandled(),
            'handled_at' => $this->handled_at?->toIso8601String(),
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler?->name),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
