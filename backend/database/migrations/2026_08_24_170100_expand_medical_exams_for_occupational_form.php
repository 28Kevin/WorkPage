<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el examen con los campos del formato de evaluacion medica
 * ocupacional para trabajo en alturas y espacios confinados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            // Identificacion del trabajador
            $table->string('document_type', 5)->default('CC');
            $table->string('sex', 1)->nullable();
            $table->foreignId('afp_id')->nullable()->constrained('afps')->nullOnDelete();

            // Empleador y puesto
            $table->string('client_company')->nullable();
            $table->string('economic_activity')->nullable();

            // Conceptos de aptitud: el formato pide uno por cada tipo de tarea.
            $table->string('aptitude_position')->default('APTO');
            $table->string('aptitude_heights')->nullable();
            $table->string('aptitude_confined')->nullable();

            // Hallazgos, restricciones y su vigencia
            $table->text('clinical_findings')->nullable();
            $table->text('restrictions')->nullable();
            $table->string('restrictions_validity')->nullable();

            // Paraclinicos: realizado, concepto y resultado por prueba.
            $table->json('paraclinicals')->nullable();

            $table->boolean('consent_accepted')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('afp_id');

            $table->dropColumn([
                'document_type',
                'sex',
                'client_company',
                'economic_activity',
                'aptitude_position',
                'aptitude_heights',
                'aptitude_confined',
                'clinical_findings',
                'restrictions',
                'restrictions_validity',
                'paraclinicals',
                'consent_accepted',
            ]);
        });
    }
};
