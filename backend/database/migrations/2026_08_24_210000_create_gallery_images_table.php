<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotografias del centro medico que se muestran en las paginas publicas.
 * Van en la base y no en disco porque el almacenamiento del servidor es efimero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('caption')->nullable();
            $table->longText('image');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
