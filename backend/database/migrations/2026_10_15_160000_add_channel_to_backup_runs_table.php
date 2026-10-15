<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por donde se obtuvo el respaldo. La descarga directa pasa a ser la via
 * principal; el correo se conserva para dejar una copia fuera del servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            // 'download' | 'mail'
            $table->string('channel', 20)->default('mail')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
