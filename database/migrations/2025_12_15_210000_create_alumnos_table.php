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
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            $table->string('nombre');
            $table->string('apellido_paterno');
            $table->string('apellido_materno')->nullable();
            $table->string('curp', 18)->nullable()->unique();
            $table->date('fecha_nacimiento');
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->onDelete('set null');
            $table->string('foto_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['escuela_id', 'activo']);
            $table->index(['escuela_id', 'grupo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
