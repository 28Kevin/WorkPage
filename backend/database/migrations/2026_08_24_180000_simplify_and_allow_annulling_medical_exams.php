<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes al formato: contacto y EPS dejan de ser obligatorios, aparece el
 * trabajador independiente (sin NIT) y los examenes se anulan en vez de
 * borrarse, para que el QR ya impreso siga teniendo una respuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('company_nit')->nullable()->change();
            $table->foreignId('eps_id')->nullable()->change();

            $table->boolean('is_independent')->default(false);

            $table->timestamp('annulled_at')->nullable();
            $table->text('annulment_reason')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('medical_exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('annulled_by');
            $table->dropColumn(['is_independent', 'annulled_at', 'annulment_reason']);

            $table->string('email')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('company_nit')->nullable(false)->change();
            $table->foreignId('eps_id')->nullable(false)->change();
        });
    }
};
