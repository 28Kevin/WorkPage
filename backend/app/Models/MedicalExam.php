<?php

namespace App\Models;

use App\Enums\ExamResult;
use App\Enums\ExamType;
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
        'document_number',
        'birth_date',
        'email',
        'phone',
        'height_cm',
        'ideal_weight_kg',
        'weight_kg',
        'company_name',
        'company_nit',
        'eps_id',
        'arl_id',
        'city_id',
        'position',
        'exam_date',
        'exam_type',
        'medical_parameters',
        'result',
        'recommendations',
        'verification_code',
        'issued_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'exam_date' => 'date',
            'issued_at' => 'datetime',
            'medical_parameters' => 'array',
            'exam_type' => ExamType::class,
            'result' => ExamResult::class,
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

    public function getAgeAttribute(): int
    {
        return $this->birth_date->diffInYears($this->exam_date);
    }

    public function verificationUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verificar/'.$this->verification_code;
    }
}
