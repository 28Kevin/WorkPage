<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La ciudad deja de ser obligatoria al registrar la evaluacion. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable(false)->change();
        });
    }
};
