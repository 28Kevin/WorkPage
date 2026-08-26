<?php

namespace App\Http\Resources;

use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GalleryImage */
class GalleryImageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'caption' => $this->caption,
            // La imagen viaja como URL, no incrustada: asi el navegador la cachea
            // y no se vuelve a descargar en cada visita.
            'url' => $this->fileUrl(),
            'position' => $this->position,
            'active' => $this->active,
        ];
    }
}
