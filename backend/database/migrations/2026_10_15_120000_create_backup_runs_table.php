<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de intentos de respaldo.
 *
 * Un respaldo que falla en silencio es peor que no tenerlo: da la impresion de
 * estar cubierto. Guardar cada intento, con su resultado, permite ver en el
 * panel si el ultimo salio bien y desde cuando no hay una copia buena.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();

            // 'ok' | 'failed'
            $table->string('status', 20)->index();

            // Si el correo salio de verdad del servidor. Con el driver 'log'
            // Laravel no lanza error pero tampoco entrega nada.
            $table->boolean('delivered')->default(false);
            $table->string('mailer', 30)->nullable();
            $table->string('recipient', 150)->nullable();

            $table->string('filename')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
