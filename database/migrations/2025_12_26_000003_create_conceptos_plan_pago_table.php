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
        Schema::create('conceptos_plan_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_pago_id')->constrained('planes_pago')->onDelete('cascade');
            $table->string('concepto'); // Ej: "Inscripción", "Colegiatura Enero"
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0); // Para ordenar los conceptos

            // Precios
            $table->decimal('monto_base', 10, 2); // Precio normal
            $table->decimal('monto_pronto_pago', 10, 2)->nullable(); // Con descuento
            $table->decimal('monto_recargo', 10, 2)->nullable(); // Recargo adicional

            // Fechas
            $table->date('fecha_vencimiento'); // Fecha límite para pago normal
            $table->date('fecha_pronto_pago')->nullable(); // Hasta cuándo aplica descuento
            $table->date('fecha_recargo')->nullable(); // Desde cuándo se aplica recargo

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['plan_pago_id', 'orden']);
            $table->index(['activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conceptos_plan_pago');
    }
};
