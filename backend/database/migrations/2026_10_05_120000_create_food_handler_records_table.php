<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete de manipulacion de alimentos: segundo tipo de documento de la
 * plataforma. Tabla aparte del examen ocupacional porque no comparten campos
 * y el formato cambia por su cuenta; lo comun (serial, verificacion, anulacion)
 * se repite con los mismos nombres para que el modulo publico los trate igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_handler_records', function (Blueprint $table) {
            $table->id();

            // Consecutivo propio: comparte la tabla exam_sequences con otra clave.
            $table->unsignedBigInteger('order_number')->unique();
            $table->string('order_code')->unique();

            // Datos del paciente: se escriben una vez y llenan las cinco paginas.
            $table->date('issue_date');
            $table->string('full_name', 150);
            $table->string('document_number', 20);
            $table->unsignedSmallInteger('age');
            // En base y no en disco: el almacenamiento del servidor es efimero.
            $table->longText('photo')->nullable();

            // Un campo por valor de laboratorio, precargado en su valor normal.
            $table->json('lab_results')->nullable();

            $table->string('verification_code')->unique();
            $table->timestamp('issued_at');

            // Se anula sin borrar: el QR ya impreso debe seguir respondiendo.
            $table->timestamp('annulled_at')->nullable();
            $table->string('annulment_reason')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // La consulta publica busca por cedula y ordena por fecha.
            $table->index(['document_number', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_handler_records');
    }
};
