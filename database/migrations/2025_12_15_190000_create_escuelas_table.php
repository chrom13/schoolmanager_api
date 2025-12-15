<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('escuelas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('cct')->unique()->comment('Clave de Centro de Trabajo (SEP)');
            $table->string('rfc', 13)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('regimen_fiscal')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('cct');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escuelas');
    }
};
