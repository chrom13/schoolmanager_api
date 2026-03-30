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
        // Actualizar tabla conceptos_plantilla
        // Usar hasColumn para compatibilidad con migrate:fresh (las columnas pueden no existir
        // si la migración de creación ya usa los nombres nuevos).
        Schema::table('conceptos_plantilla', function (Blueprint $table) {
            $columnas = array_filter([
                'descuento_pronto_pago_porcentaje',
                'dias_descuento_pronto_pago',
                'recargo_mora_porcentaje',
            ], fn($col) => Schema::hasColumn('conceptos_plantilla', $col));

            if (!empty($columnas)) {
                $table->dropColumn(array_values($columnas));
            }
        });

        // Actualizar tabla conceptos_plan_pago
        Schema::table('conceptos_plan_pago', function (Blueprint $table) {
            $columnas = array_filter([
                'monto',
                'descuento_pronto_pago',
                'recargo_mora',
            ], fn($col) => Schema::hasColumn('conceptos_plan_pago', $col));

            if (!empty($columnas)) {
                $table->dropColumn(array_values($columnas));
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conceptos_plantilla', function (Blueprint $table) {
            $table->decimal('descuento_pronto_pago_porcentaje', 5, 2)->nullable();
            $table->integer('dias_descuento_pronto_pago')->nullable();
            $table->decimal('recargo_mora_porcentaje', 5, 2)->nullable();
        });

        Schema::table('conceptos_plan_pago', function (Blueprint $table) {
            $table->decimal('monto', 10, 2);
            $table->decimal('descuento_pronto_pago', 10, 2)->nullable();
            $table->decimal('recargo_mora', 10, 2)->nullable();
        });
    }
};
