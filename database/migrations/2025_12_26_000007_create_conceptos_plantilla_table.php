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
        Schema::create('conceptos_plantilla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_plan_pago_id')->constrained('plantillas_plan_pago')->onDelete('cascade');
            $table->string('concepto'); // Ej: "Inscripción", "Colegiatura {mes}"
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0);

            // Tipo de concepto
            $table->enum('tipo_concepto', ['inscripcion', 'colegiatura', 'examen', 'otro'])->default('otro');

            // Mes relativo (1-12, null si no aplica)
            $table->integer('mes_relativo')->nullable(); // 1=Enero, 7=Julio, etc.

            // Monto sugerido (opcional, puede ser 0)
            $table->decimal('monto_sugerido', 10, 2)->default(0);

            // Configuración de fechas (relativa)
            $table->integer('dia_vencimiento')->default(10); // Día del mes en que vence (1-31)

            // Descuento por pronto pago
            $table->decimal('descuento_pronto_pago_porcentaje', 5, 2)->nullable(); // Ej: 5.00 = 5%
            $table->integer('dias_pronto_pago_antes_vencimiento')->nullable(); // Ej: 5 días antes

            // Recargo por pago tardío
            $table->decimal('recargo_porcentaje', 5, 2)->nullable(); // Ej: 10.00 = 10%
            $table->integer('dias_recargo_despues_vencimiento')->nullable(); // Ej: 10 días después

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['plantilla_plan_pago_id', 'orden']);
            $table->index(['tipo_concepto']);
            $table->index(['activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conceptos_plantilla');
    }
};
