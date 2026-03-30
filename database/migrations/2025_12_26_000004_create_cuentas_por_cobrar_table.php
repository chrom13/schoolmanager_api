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
        Schema::create('cuentas_por_cobrar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');

            // Relación con plan (nullable para cargos sueltos)
            $table->foreignId('concepto_plan_id')
                ->nullable()
                ->constrained('conceptos_plan_pago')
                ->onDelete('set null');

            $table->string('concepto'); // Descripción del cargo
            $table->text('descripcion')->nullable();

            // Precios (copiados del plan o definidos manualmente)
            $table->decimal('monto_base', 10, 2);
            $table->decimal('monto_pronto_pago', 10, 2)->nullable();
            $table->decimal('monto_recargo', 10, 2)->nullable();

            // Fechas (copiadas del plan o definidas manualmente)
            $table->date('fecha_vencimiento');
            $table->date('fecha_pronto_pago')->nullable();
            $table->date('fecha_recargo')->nullable();

            // Control de pagos
            $table->enum('estado', ['pendiente', 'pagado', 'vencido', 'cancelado'])->default('pendiente');
            $table->decimal('monto_pagado', 10, 2)->default(0);
            $table->decimal('saldo', 10, 2); // Calculado: monto_actual - monto_pagado
            $table->date('fecha_pago')->nullable();

            // Metadatos
            $table->boolean('es_cargo_suelto')->default(false); // True si no viene de un plan
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['escuela_id', 'alumno_id']);
            $table->index(['estado']);
            $table->index(['fecha_vencimiento']);
            $table->index(['es_cargo_suelto']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_cobrar');
    }
};
