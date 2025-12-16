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
        Schema::create('materia_grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materia_id')->constrained('materias')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->foreignId('maestro_id')->nullable()->constrained('usuarios')->onDelete('set null');
            $table->integer('horas_semanales')->nullable()->comment('Horas de clase por semana');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['materia_id', 'grupo_id']);
            $table->index(['grupo_id', 'activo']);
            $table->index('maestro_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materia_grupo');
    }
};
