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
        Schema::create('conceptos_cobro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            $table->string('nombre')->comment('Ej: Colegiatura, Inscripción, Uniforme');
            $table->text('descripcion')->nullable();
            $table->decimal('precio_base', 10, 2)->comment('Precio por defecto');
            $table->enum('periodicidad', ['unico', 'mensual', 'bimestral', 'trimestral', 'cuatrimestral', 'semestral', 'anual'])->default('mensual');
            $table->foreignId('nivel_id')->nullable()->constrained('niveles')->onDelete('set null')->comment('Si aplica solo a un nivel');
            $table->foreignId('grado_id')->nullable()->constrained('grados')->onDelete('set null')->comment('Si aplica solo a un grado');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['escuela_id', 'activo']);
            $table->index(['nivel_id', 'grado_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conceptos_cobro');
    }
};
