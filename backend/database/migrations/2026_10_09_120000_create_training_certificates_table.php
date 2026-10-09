<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificado de capacitacion (trabajo en alturas): tercer tipo de documento.
 * El PDF se arma sobre la plantilla de guia, asi que aqui solo viven los datos
 * que cambian de un certificado a otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_certificates', function (Blueprint $table) {
            $table->id();

            // Consecutivo propio: comparte exam_sequences con otra clave.
            $table->unsignedBigInteger('order_number')->unique();
            $table->string('order_code')->unique();

            $table->string('participant_name', 150);
            $table->string('document_number', 20);
            $table->string('course', 120);
            $table->string('city', 80);

            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('hours');
            $table->date('signed_date');

            // Empresa que respalda la certificacion. No va en el PDF: se
            // muestra en la pagina publica a la que lleva el QR.
            $table->string('company_nit', 40)->nullable();
            $table->longText('company_logo')->nullable();

            $table->string('verification_code')->unique();
            $table->timestamp('issued_at');

            $table->timestamp('annulled_at')->nullable();
            $table->string('annulment_reason')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_number', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_certificates');
    }
};
