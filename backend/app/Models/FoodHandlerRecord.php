<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paquete de manipulacion de alimentos. Repite a proposito los nombres de
 * MedicalExam en lo comun (order_code, verification_code, anulacion) para que
 * la consulta publica pueda tratar los dos documentos de la misma forma.
 */
class FoodHandlerRecord extends Model
{
    use HasFactory;

    /** Etiqueta del tipo de documento en la consulta publica. */
    public const TYPE = 'food_handler';

    public const TYPE_LABEL = 'Paquete de manipulación de alimentos';

    protected $fillable = [
        'order_number',
        'order_code',
        'issue_date',
        'full_name',
        'document_number',
        'age',
        'photo',
        'lab_results',
        'verification_code',
        'issued_at',
        'annulled_at',
        'annulment_reason',
        'annulled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'issued_at' => 'datetime',
            'annulled_at' => 'datetime',
            'lab_results' => 'array',
            'age' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    /** Los documentos anulados no aparecen en los listados por defecto. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('annulled_at');
    }

    public function scopeAnnulled(Builder $query): Builder
    {
        return $query->whereNotNull('annulled_at');
    }

    public function isAnnulled(): bool
    {
        return $this->annulled_at !== null;
    }

    /** Mismo destino que el examen ocupacional: una sola pagina resuelve ambos. */
    public function verificationUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verificar/'.$this->verification_code;
    }
}
