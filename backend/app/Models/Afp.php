<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Fondo de pensiones y cesantias al que esta afiliado el trabajador. */
class Afp extends Model
{
    use HasFactory;

    protected $table = 'afps';

    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
