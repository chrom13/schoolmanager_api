<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('matricula')->nullable()->unique()->after('escuela_id');
            $table->enum('genero', ['masculino', 'femenino', 'otro'])->nullable()->after('apellido_materno');
            $table->boolean('activo')->default(true)->after('foto_url');
            $table->string('telefono', 20)->nullable()->after('activo');
            $table->string('email')->nullable()->after('telefono');
            $table->text('direccion')->nullable()->after('email');

            $table->index(['escuela_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropIndex(['escuela_id', 'activo']);
            $table->dropColumn(['matricula', 'genero', 'activo', 'telefono', 'email', 'direccion']);
        });
    }
};
