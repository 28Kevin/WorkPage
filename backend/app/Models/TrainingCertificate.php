<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Certificado de capacitacion. Repite los nombres de MedicalExam en lo comun
 * (order_code, verification_code, anulacion) para que el modulo publico trate
 * los tres documentos igual.
 */
class TrainingCertificate extends Model
{
    use HasFactory;

    public const TYPE = 'training_certificate';

    public const TYPE_LABEL = 'Certificado de capacitación';

    protected $fillable = [
        'order_number',
        'order_code',
        'participant_name',
        'document_number',
        'course',
        'city',
        'start_date',
        'end_date',
        'hours',
        'signed_date',
        'company_nit',
        'company_logo',
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
            'start_date' => 'date',
            'end_date' => 'date',
            'signed_date' => 'date',
            'issued_at' => 'datetime',
            'annulled_at' => 'datetime',
            'hours' => 'integer',
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

    /** El QR del certificado lleva a su propia pagina de confirmacion. */
    public function verificationUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/certificado/'.$this->verification_code;
    }
}
