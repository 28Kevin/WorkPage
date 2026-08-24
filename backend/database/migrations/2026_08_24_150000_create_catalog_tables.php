<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eps', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('arls', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Enlace directo a la plataforma de la ARL para descarga de certificados.
            $table->string('certificate_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'department']);
        });

        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('arls');
        Schema::dropIfExists('eps');
    }
};
