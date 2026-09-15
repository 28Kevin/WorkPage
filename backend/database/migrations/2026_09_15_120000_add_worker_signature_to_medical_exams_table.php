<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma manuscrita del trabajador, trazada en el formulario y estampada en el
 * certificado. Igual que la fotografia: va en la base y no en disco porque el
 * almacenamiento del servidor es efimero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->longText('worker_signature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->dropColumn('worker_signature');
        });
    }
};
