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
        Schema::table('ciclos_escolares', function (Blueprint $table) {
            // 1. Add nivel_id column (nullable initially to allow gradual migration)
            $table->foreignId('nivel_id')
                ->nullable()
                ->after('escuela_id')
                ->constrained('niveles')
                ->onDelete('cascade');
        });

        // 2. Drop old unique index (escuela_id, nombre)
        Schema::table('ciclos_escolares', function (Blueprint $table) {
            $table->dropUnique(['escuela_id', 'nombre']);
        });

        // 3. Create new indexes
        Schema::table('ciclos_escolares', function (Blueprint $table) {
            // Unique constraint: same name can exist across different niveles
            $table->unique(['escuela_id', 'nivel_id', 'nombre'], 'ciclos_escuela_nivel_nombre_unique');

            // Performance index for queries filtering by nivel and active status
            $table->index(['escuela_id', 'nivel_id', 'activo'], 'ciclos_escuela_nivel_activo_idx');

            // Performance index for date overlap validation queries
            $table->index(['escuela_id', 'nivel_id', 'fecha_inicio', 'fecha_fin'], 'ciclos_escuela_nivel_fechas_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ciclos_escolares', function (Blueprint $table) {
            // Drop new indexes
            $table->dropUnique('ciclos_escuela_nivel_nombre_unique');
            $table->dropIndex('ciclos_escuela_nivel_activo_idx');
            $table->dropIndex('ciclos_escuela_nivel_fechas_idx');
        });

        // Recreate old unique index
        Schema::table('ciclos_escolares', function (Blueprint $table) {
            $table->unique(['escuela_id', 'nombre']);
        });

        // Drop nivel_id column and foreign key
        Schema::table('ciclos_escolares', function (Blueprint $table) {
            $table->dropForeign(['nivel_id']);
            $table->dropColumn('nivel_id');
        });
    }
};
