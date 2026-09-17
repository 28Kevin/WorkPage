<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ARL deja de ser obligatoria: hay trabajadores que llegan a la evaluacion
 * sin afiliacion vigente o sin el dato a mano al momento de emitir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->foreignId('arl_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->foreignId('arl_id')->nullable(false)->change();
        });
    }
};
