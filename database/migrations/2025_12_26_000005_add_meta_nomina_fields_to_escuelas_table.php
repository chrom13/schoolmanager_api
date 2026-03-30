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
        Schema::table('escuelas', function (Blueprint $table) {
            // Campos para la configuración de Meta de Nómina
            $table->decimal('costo_operativo_mensual', 10, 2)->nullable()->after('es_registro_express')
                ->comment('Costo operativo mensual de la escuela (nómina, renta, servicios, etc.)');

            $table->decimal('colegiatura_mensual', 10, 2)->nullable()->after('costo_operativo_mensual')
                ->comment('Colegiatura mensual promedio por alumno');

            $table->integer('porcentaje_tolerancia')->default(10)->after('colegiatura_mensual')
                ->comment('Porcentaje de tolerancia de morosidad aceptable (0-30)');

            $table->integer('alumnos_necesarios')->nullable()->after('porcentaje_tolerancia')
                ->comment('Número de alumnos necesarios para cubrir gastos (calculado automáticamente)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->dropColumn([
                'costo_operativo_mensual',
                'colegiatura_mensual',
                'porcentaje_tolerancia',
                'alumnos_necesarios',
            ]);
        });
    }
};
