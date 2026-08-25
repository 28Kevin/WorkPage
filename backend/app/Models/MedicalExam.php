<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\ExamResult;
use App\Enums\ExamType;
use App\Enums\Sex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MedicalExam extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_code',
        'full_name',
        'document_type',
        'document_number',
        'birth_date',
        'sex',
        'photo',
        'email',
        'phone',
        'height_cm',
        'ideal_weight_kg',
        'weight_kg',
        'company_name',
        'company_nit',
        'client_company',
        'economic_activity',
        'is_independent',
        'eps_id',
        'arl_id',
        'afp_id',
        'city_id',
        'position',
        'exam_date',
        'exam_type',
        'medical_parameters',
        'paraclinicals',
        'aptitude_position',
        'aptitude_heights',
        'aptitude_confined',
        'result',
        'clinical_findings',
        'recommendations',
        'restrictions',
        'restrictions_validity',
        'consent_accepted',
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
            'birth_date' => 'date',
            'exam_date' => 'date',
            'issued_at' => 'datetime',
            'medical_parameters' => 'array',
            'paraclinicals' => 'array',
            'document_type' => DocumentType::class,
            'sex' => Sex::class,
            'exam_type' => ExamType::class,
            'result' => ExamResult::class,
            'aptitude_position' => ExamResult::class,
            'aptitude_heights' => ExamResult::class,
            'aptitude_confined' => ExamResult::class,
            'consent_accepted' => 'boolean',
            'is_independent' => 'boolean',
            'annulled_at' => 'datetime',
            'height_cm' => 'integer',
            'ideal_weight_kg' => 'decimal:2',
            'weight_kg' => 'decimal:2',
        ];
    }

    public function eps(): BelongsTo
    {
        return $this->belongsTo(Eps::class);
    }

    public function arl(): BelongsTo
    {
        return $this->belongsTo(Arl::class);
    }

    public function afp(): BelongsTo
    {
        return $this->belongsTo(Afp::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function risks(): BelongsToMany
    {
        return $this->belongsToMany(Risk::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Los examenes anulados no aparecen en los listados por defecto. */
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

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public function getAgeAttribute(): int
    {
        return $this->birth_date->diffInYears($this->exam_date);
    }

    public function verificationUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verificar/'.$this->verification_code;
    }
}
