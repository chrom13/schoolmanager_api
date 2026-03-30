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
        Schema::create('concepto_precios', function (Blueprint $table) {
            $table->id();

            // Relación polimórfica: puede pertenecer a ConceptoPlantilla o ConceptoPlanPago
            $table->morphs('concepto');

            // Tipo de precio
            $table->enum('tipo', ['fecha_fija', 'dias_vencimiento'])->default('dias_vencimiento');

            // Para tipo 'fecha_fija' (ej: inscripciones escalonadas por mes)
            $table->date('desde_fecha')->nullable();
            $table->date('hasta_fecha')->nullable();

            // Para tipo 'dias_vencimiento' (ej: descuentos/recargos relativos)
            // Valores negativos = antes del vencimiento, positivos = después
            $table->integer('desde_dias')->nullable();
            $table->integer('hasta_dias')->nullable();

            // Monto a aplicar en este rango
            $table->decimal('monto', 10, 2);

            // Descripción opcional del rango (ej: "Pago anticipado", "Recargo 1 mes")
            $table->string('descripcion')->nullable();

            // Orden para mostrar los precios
            $table->integer('orden')->default(0);

            $table->timestamps();

            // Índice adicional para tipo
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concepto_precios');
    }
};
