<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'handled_at', 'handled_by'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('handled_at');
    }

    public function scopeHandled(Builder $query): Builder
    {
        return $query->whereNotNull('handled_at');
    }

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
