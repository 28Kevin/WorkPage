<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cuenta de cobro emitida. */
class BillingAccount extends Model
{
    use HasFactory;

    public const TYPE = 'billing_account';

    public const TYPE_LABEL = 'Cuenta de cobro';

    protected $fillable = [
        'number',
        'number_code',
        'issue_place',
        'issue_date',
        'billing_company_id',
        'company_name',
        'company_nit',
        'company_address',
        'company_city',
        'items',
        'total',
        'annulled_at',
        'annulment_reason',
        'annulled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'annulled_at' => 'datetime',
            'items' => 'array',
            'total' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(BillingCompany::class, 'billing_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
}
