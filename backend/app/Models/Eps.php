<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eps extends Model
{
    use HasFactory;

    protected $table = 'eps';

    protected $fillable = ['name', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
