<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Empresa a la que se le cobra, guardada para reutilizarla. */
class BillingCompany extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'nit', 'address', 'city'];
}
