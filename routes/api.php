<?php

use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalificacionController;
use App\Http\Controllers\Api\V1\CicloEscolarController;
use App\Http\Controllers\Api\V1\ConceptoCobroController;
use App\Http\Controllers\Api\V1\GradoController;
use App\Http\Controllers\Api\V1\GrupoController;
use App\Http\Controllers\Api\V1\MateriaController;
use App\Http\Controllers\Api\V1\NivelController;
use App\Http\Controllers\Api\V1\PadreController;
use App\Http\Controllers\Api\V1\PeriodoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Test endpoint
Route::get('/test', function () {
    return response()->json([
        'message' => 'School Manager API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Estructura Académica
    Route::apiResource('niveles', NivelController::class);
    Route::apiResource('grados', GradoController::class);
    Route::apiResource('grupos', GrupoController::class);

    // Alumnos y Padres
    Route::apiResource('alumnos', AlumnoController::class);
    Route::apiResource('padres', PadreController::class);

    // Materias
    Route::apiResource('materias', MateriaController::class);
    Route::post('materias/{materia}/asignar-grupo', [MateriaController::class, 'asignarGrupo']);
    Route::put('materias/{materia}/grupos/{grupoId}', [MateriaController::class, 'actualizarAsignacion']);
    Route::delete('materias/{materia}/grupos/{grupoId}', [MateriaController::class, 'desasignarGrupo']);

    // Ciclos Escolares y Períodos
    Route::apiResource('ciclos-escolares', CicloEscolarController::class);
    Route::apiResource('periodos', PeriodoController::class);

    // Calificaciones
    Route::apiResource('calificaciones', CalificacionController::class);
    Route::get('alumnos/{alumnoId}/boleta', [CalificacionController::class, 'boleta']);

    // Asistencias
    Route::apiResource('asistencias', AsistenciaController::class);
    Route::post('grupos/{grupoId}/asistencias', [AsistenciaController::class, 'registrarGrupo']);
    Route::get('alumnos/{alumnoId}/reporte-asistencias', [AsistenciaController::class, 'reporteAlumno']);

    // Conceptos de Cobro
    Route::apiResource('conceptos-cobro', ConceptoCobroController::class);
});
