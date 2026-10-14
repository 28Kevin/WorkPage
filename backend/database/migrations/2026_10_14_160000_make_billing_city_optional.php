<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ciudad de la empresa deja de ser obligatoria en la cuenta de cobro: la
 * direccion suele traerla y no siempre se conoce aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_companies', function (Blueprint $table) {
            $table->string('city', 80)->nullable()->change();
        });

        Schema::table('billing_accounts', function (Blueprint $table) {
            $table->string('company_city', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('billing_companies', function (Blueprint $table) {
            $table->string('city', 80)->nullable(false)->change();
        });

        Schema::table('billing_accounts', function (Blueprint $table) {
            $table->string('company_city', 80)->nullable(false)->change();
        });
    }
};
