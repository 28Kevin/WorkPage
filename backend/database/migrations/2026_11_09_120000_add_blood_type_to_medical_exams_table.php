<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nullable: los examenes ya emitidos no registraron el RH. */
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->string('blood_type', 3)->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->dropColumn('blood_type');
        });
    }
};
