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
        Schema::table('grupos', function (Blueprint $table) {
            $table->foreignId('ciclo_escolar_id')
                ->nullable()
                ->after('grado_id')
                ->constrained('ciclos_escolares')
                ->onDelete('cascade');

            // Agregar índice para mejor rendimiento
            $table->index(['escuela_id', 'ciclo_escolar_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropForeign(['ciclo_escolar_id']);
            $table->dropIndex(['escuela_id', 'ciclo_escolar_id']);
            $table->dropColumn('ciclo_escolar_id');
        });
    }
};
