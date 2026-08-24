<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_exams', function (Blueprint $table) {
            $table->id();

            // Numeracion consecutiva automatica y ascendente.
            $table->unsignedBigInteger('order_number')->unique();
            $table->string('order_code')->unique();

            // A. Datos del trabajador
            $table->string('full_name');
            $table->string('document_number')->index();
            $table->date('birth_date');
            $table->string('email');
            $table->string('phone');
            $table->unsignedSmallInteger('height_cm');
            $table->decimal('ideal_weight_kg', 5, 2);
            $table->decimal('weight_kg', 5, 2);

            // B. Datos ocupacionales y de afiliacion
            $table->string('company_name');
            $table->string('company_nit');
            $table->foreignId('eps_id')->constrained('eps');
            $table->foreignId('arl_id')->constrained('arls');
            $table->foreignId('city_id')->constrained('cities');
            $table->string('position');

            // C. Detalles del examen
            $table->date('exam_date');
            $table->string('exam_type');

            // Parametros medicos autodiligenciados dentro de rangos normales.
            $table->json('medical_parameters');
            $table->string('result')->default('APTO');
            $table->text('recommendations')->nullable();

            // Verificacion publica via codigo QR.
            $table->string('verification_code', 64)->unique();
            $table->timestamp('issued_at');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_exams');
    }
};
