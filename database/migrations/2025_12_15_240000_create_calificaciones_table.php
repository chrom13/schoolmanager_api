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
        Schema::create('calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('materia_id')->constrained('materias')->onDelete('cascade');
            $table->foreignId('periodo_id')->constrained('periodos')->onDelete('cascade');
            $table->decimal('calificacion', 5, 2)->comment('Calificación numérica');
            $table->text('observaciones')->nullable();
            $table->foreignId('maestro_id')->nullable()->constrained('usuarios')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->unique(['alumno_id', 'materia_id', 'periodo_id']);
            $table->index(['periodo_id', 'materia_id']);
            $table->index('maestro_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calificaciones');
    }
};
