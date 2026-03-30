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
        Schema::create('plantillas_plan_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->nullable()->constrained('escuelas')->onDelete('cascade');
            $table->foreignId('nivel_id')->constrained('niveles')->onDelete('cascade');
            $table->string('nombre'); // Ej: "Plan General Secundaria", "Plan con Becas 50%"
            $table->text('descripcion')->nullable();
            $table->boolean('es_sistema')->default(false); // true = plantilla predefinida del sistema
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['escuela_id', 'nivel_id', 'activo']);
            $table->index(['es_sistema']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantillas_plan_pago');
    }
};
