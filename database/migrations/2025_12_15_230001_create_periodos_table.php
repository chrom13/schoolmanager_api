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
        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_escolar_id')->constrained('ciclos_escolares')->onDelete('cascade');
            $table->string('nombre')->comment('Ej: 1er Bimestre, Trimestre 1');
            $table->integer('numero')->comment('Orden del período: 1, 2, 3...');
            $table->enum('tipo', ['bimestre', 'trimestre', 'cuatrimestre', 'semestre', 'anual'])->default('bimestre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['ciclo_escolar_id', 'numero']);
            $table->index(['ciclo_escolar_id', 'activo']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
