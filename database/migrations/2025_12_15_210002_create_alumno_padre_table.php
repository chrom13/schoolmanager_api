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
        Schema::create('alumno_padre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('padre_id')->constrained('padres')->onDelete('cascade');
            $table->enum('parentesco', ['padre', 'madre', 'tutor', 'abuelo', 'otro'])->default('padre');
            $table->boolean('responsable_pagos')->default(false);
            $table->boolean('contacto_emergencia')->default(false);
            $table->timestamps();

            // Indexes
            $table->unique(['alumno_id', 'padre_id']);
            $table->index(['padre_id', 'responsable_pagos']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumno_padre');
    }
};
