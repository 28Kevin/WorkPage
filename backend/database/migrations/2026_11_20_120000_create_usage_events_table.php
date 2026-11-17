<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eventos de uso ya agregados: una fila por metrica, sujeto y hora, con
     * su conteo. Nunca una fila por evento, para no crecer con cada visita.
     */
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('bucket');
            $table->string('metric', 40);
            // Cadena vacia y no NULL: en PostgreSQL los NULL no chocan en el indice unico.
            $table->string('subject', 80)->default('');
            $table->unsignedInteger('count')->default(0);

            $table->unique(['bucket', 'metric', 'subject']);
            $table->index(['metric', 'bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
