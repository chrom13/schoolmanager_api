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
            // Hacer CCT nullable para permitir valores temporales en registro express
            $table->string('cct')->nullable()->change();

            // Campos de tracking de onboarding
            $table->boolean('onboarding_completado')->default(false)->after('activo');
            $table->json('onboarding_data')->nullable()->after('onboarding_completado');
            $table->timestamp('onboarding_completado_at')->nullable()->after('onboarding_data');
            $table->boolean('es_registro_express')->default(false)->after('onboarding_completado_at');

            // Índice para queries de escuelas con onboarding pendiente
            $table->index(['onboarding_completado', 'created_at'], 'idx_onboarding_pendiente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            // Eliminar índice
            $table->dropIndex('idx_onboarding_pendiente');

            // Eliminar campos de onboarding
            $table->dropColumn([
                'onboarding_completado',
                'onboarding_data',
                'onboarding_completado_at',
                'es_registro_express',
            ]);

            // Revertir CCT a NOT NULL (cuidado: esto podría fallar si hay NULLs)
            // Solo descomentar si es seguro hacerlo
            // $table->string('cct')->nullable(false)->change();
        });
    }
};
