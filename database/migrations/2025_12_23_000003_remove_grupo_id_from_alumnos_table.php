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
        Schema::table('alumnos', function (Blueprint $table) {
            // Remover índice que incluye grupo_id
            $table->dropIndex(['escuela_id', 'grupo_id']);

            // Remover foreign key constraint
            $table->dropForeign(['grupo_id']);

            // Remover columna grupo_id
            $table->dropColumn('grupo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            // Restaurar columna grupo_id
            $table->foreignId('grupo_id')
                ->nullable()
                ->after('fecha_nacimiento')
                ->constrained('grupos')
                ->onDelete('set null');

            // Restaurar índice
            $table->index(['escuela_id', 'grupo_id']);
        });
    }
};
