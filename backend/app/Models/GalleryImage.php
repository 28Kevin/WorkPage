<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'caption', 'image', 'position', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('position')->orderBy('id');
    }

    /**
     * Separa el data URI guardado en tipo y bytes, para poder servir la imagen
     * como archivo cacheable en vez de incrustarla en cada respuesta JSON.
     *
     * @return array{mime: string, contents: string}|null
     */
    public function binary(): ?array
    {
        if (! preg_match('#^data:(image/[a-z+.-]+);base64,(.*)$#is', (string) $this->image, $matches)) {
            return null;
        }

        $contents = base64_decode($matches[2], true);

        return $contents === false ? null : ['mime' => $matches[1], 'contents' => $contents];
    }

    /**
     * URL del archivo. Lleva la marca de tiempo para que al reemplazar la
     * imagen cambie la direccion y el navegador no sirva la version vieja.
     */
    public function fileUrl(): string
    {
        return route('api.gallery.file', [
            'image' => $this->id,
            'v' => $this->updated_at?->timestamp ?? 0,
        ]);
    }
}
