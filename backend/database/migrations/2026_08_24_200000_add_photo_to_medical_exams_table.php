<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotografia opcional del trabajador, impresa junto al titulo del certificado.
 * Va en la base y no en disco porque el almacenamiento del servidor es efimero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->longText('photo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
