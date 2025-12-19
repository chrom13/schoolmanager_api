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
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            $table->string('nombre');
            $table->string('clave')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('color')->nullable()->comment('Color hex para UI');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['escuela_id', 'nombre']);
            $table->index(['escuela_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
