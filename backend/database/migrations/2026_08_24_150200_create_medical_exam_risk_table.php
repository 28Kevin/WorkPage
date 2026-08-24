<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_exam_risk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->unique(['medical_exam_id', 'risk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_exam_risk');
    }
};
