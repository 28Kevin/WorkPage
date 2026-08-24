<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Arl extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'certificate_url', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
