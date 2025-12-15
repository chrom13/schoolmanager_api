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
        Schema::create('padres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            $table->string('nombre_completo');
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('regimen_fiscal')->nullable();
            $table->string('uso_cfdi')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['escuela_id', 'email']);
            $table->index(['escuela_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('padres');
    }
};
