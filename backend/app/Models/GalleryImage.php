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
}
