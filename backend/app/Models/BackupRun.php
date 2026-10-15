<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un intento de respaldo, haya salido bien o mal. */
class BackupRun extends Model
{
    use HasFactory;

    public const OK = 'ok';

    public const FAILED = 'failed';

    protected $fillable = [
        'status', 'delivered', 'mailer', 'recipient',
        'filename', 'bytes', 'error', 'created_by',
    ];

    protected function casts(): array
    {
        return ['delivered' => 'boolean', 'bytes' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function succeeded(): bool
    {
        return $this->status === self::OK;
    }
}
