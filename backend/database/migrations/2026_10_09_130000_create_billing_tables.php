<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas de cobro para empresas: el cuarto documento de la plataforma.
 *
 * Son tres tablas: las empresas que se guardan para reutilizarlas, los
 * conceptos que alimentan la lista desplegable y las cuentas emitidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Empresas a las que se les cobra. Se guardan para elegirlas luego.
        Schema::create('billing_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('nit', 40);
            $table->string('address', 180);
            $table->string('city', 80);
            $table->timestamps();

            // El NIT identifica a la empresa: evita duplicarla al reutilizarla.
            $table->unique('nit');
        });

        // Opciones de la lista desplegable de items, editables desde el panel.
        Schema::create('billing_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->id();

            // Consecutivo de cinco digitos ("06010"), sin repetirse.
            $table->unsignedBigInteger('number')->unique();
            $table->string('number_code', 10)->unique();

            $table->string('issue_place', 80);
            $table->date('issue_date');

            // La empresa queda copiada en la cuenta: si luego se corrigen sus
            // datos, un documento ya emitido no debe cambiar.
            $table->foreignId('billing_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name', 150);
            $table->string('company_nit', 40);
            $table->string('company_address', 180);
            $table->string('company_city', 80);

            // Items copiados igual: los conceptos y los precios cambian con el
            // tiempo y el total impreso tiene que seguir cuadrando.
            $table->json('items');
            $table->unsignedBigInteger('total');

            $table->timestamp('annulled_at')->nullable();
            $table->string('annulment_reason')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('company_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_accounts');
        Schema::dropIfExists('billing_concepts');
        Schema::dropIfExists('billing_companies');
    }
};
