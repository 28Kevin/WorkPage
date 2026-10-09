<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Opcion de la lista desplegable de items, editable desde el panel. */
class BillingConcept extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'position', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'position' => 'integer'];
    }

    /** Las desactivadas salen del desplegable sin perder las ya emitidas. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
