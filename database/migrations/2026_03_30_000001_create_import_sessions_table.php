<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('ciclo_escolar_id')->nullable()->constrained('ciclos_escolares')->nullOnDelete();

            // Archivo
            $table->string('archivo_path');
            $table->string('archivo_nombre_original');

            // Paso 1: Selección de hoja
            $table->string('hoja_seleccionada')->nullable();
            $table->json('hojas_disponibles')->nullable(); // [{nombre, columnas[], preview[][]}]

            // Paso 2: Mapeo de columnas
            $table->json('mapeo_columnas')->nullable(); // {excel_col => campo_alumno}

            // Paso 3: Preview y validación
            $table->json('alumnos_parseados')->nullable(); // [{temp_id, nombre, ..., es_valido, errores[]}]
            $table->json('grupos_a_crear')->nullable();    // [{grado_nombre, grupo_letra, grado_id}]

            // Paso 4: Plan general
            $table->unsignedBigInteger('plan_general_id')->nullable(); // FK lógica a planes_pago

            // Paso 5: Planes por alumno (solo overrides)
            $table->json('planes_por_alumno')->nullable(); // [{temp_id, plan_pago_id}]

            // Paso 6: Conciliación de adeudos
            $table->json('morosos')->nullable(); // {por_alumno:[{temp_id, concepto_plan_pago_ids[]}]}

            // Control de estado
            // Estados: pending | mapped | previewed | plan_assigned | conciliated | confirmed | imported | cancelled
            $table->string('status')->default('pending');
            $table->timestamp('confirmado_at')->nullable();
            $table->foreignId('confirmado_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['escuela_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
