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
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->foreignId('ciclo_escolar_id')->constrained('ciclos_escolares')->onDelete('cascade');
            $table->date('fecha_inscripcion');
            $table->enum('estado', ['activa', 'baja', 'transferido'])->default('activa');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices para búsquedas frecuentes
            $table->index(['alumno_id', 'ciclo_escolar_id']);
            $table->index(['grupo_id', 'ciclo_escolar_id']);

            // Asegurar que un alumno no esté inscrito dos veces en el mismo ciclo
            $table->unique(['alumno_id', 'ciclo_escolar_id', 'deleted_at'], 'unique_alumno_ciclo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
