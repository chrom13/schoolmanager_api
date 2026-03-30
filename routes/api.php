<?php

use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalificacionController;
use App\Http\Controllers\Api\V1\CicloEscolarController;
use App\Http\Controllers\Api\V1\ConceptoCobroController;
use App\Http\Controllers\Api\V1\ConceptoPlanPagoController;
use App\Http\Controllers\Api\V1\EscuelaController;
use App\Http\Controllers\Api\V1\FinanzasAlumnoController;
use App\Http\Controllers\Api\V1\GradoController;
use App\Http\Controllers\Api\V1\GrupoController;
use App\Http\Controllers\Api\V1\InscripcionController;
use App\Http\Controllers\Api\V1\MateriaController;
use App\Http\Controllers\Api\V1\NivelController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OnboardingImportController;
use App\Http\Controllers\Api\V1\PadreController;
use App\Http\Controllers\Api\V1\PeriodoController;
use App\Http\Controllers\Api\V1\PlanPagoController;
use App\Http\Controllers\Api\V1\PlantillaPlanPagoController;
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
    Route::post('register-express', [AuthController::class, 'registerExpress']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes that don't require email verification
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    });

    // Routes that require email verification
    Route::middleware('verified')->group(function () {
        // Estructura Académica
        Route::apiResource('niveles', NivelController::class);
        Route::apiResource('grados', GradoController::class);
        Route::apiResource('grupos', GrupoController::class);
        Route::get('grupos/{grupo}/materias', [GrupoController::class, 'materias']);
        Route::get('grupos/{grupo}/alumnos', [GrupoController::class, 'alumnos']);
        Route::apiResource('inscripciones', InscripcionController::class);

        // Alumnos y Padres
        Route::get('alumnos/plantilla-importacion', [AlumnoController::class, 'downloadTemplate']);
        Route::post('alumnos/importar', [AlumnoController::class, 'import']);
        Route::apiResource('alumnos', AlumnoController::class);
        Route::apiResource('padres', PadreController::class);

        // Onboarding — setup inicial
        Route::prefix('onboarding')->group(function () {
            Route::get('status', [OnboardingController::class, 'status']);
            Route::post('complete-school-data', [OnboardingController::class, 'completeSchoolData']);
            Route::post('complete-structure', [OnboardingController::class, 'completeStructure']);
            Route::post('complete', [OnboardingController::class, 'complete']);
            Route::post('skip', [OnboardingController::class, 'skip']);

            // Importación de alumnos con conciliación financiera
            Route::prefix('import')->group(function () {
                Route::get('session-activa', [OnboardingImportController::class, 'sessionActiva']);
                Route::post('upload', [OnboardingImportController::class, 'upload']);
                Route::get('{session}', [OnboardingImportController::class, 'show']);
                Route::post('{session}/hoja', [OnboardingImportController::class, 'seleccionarHoja']);
                Route::post('{session}/mapeo', [OnboardingImportController::class, 'mapeo']);
                Route::post('{session}/preview', [OnboardingImportController::class, 'preview']);
                Route::post('{session}/resolver-grupos', [OnboardingImportController::class, 'resolverGrupos']);
                Route::get('{session}/planes', [OnboardingImportController::class, 'planes']);
                Route::post('{session}/plan-general', [OnboardingImportController::class, 'planGeneral']);
                Route::post('{session}/planes-por-alumno', [OnboardingImportController::class, 'planesPorAlumno']);
                Route::post('{session}/morosos', [OnboardingImportController::class, 'morosos']);
                Route::post('{session}/confirmar', [OnboardingImportController::class, 'confirmar']);
                Route::delete('{session}', [OnboardingImportController::class, 'cancelar']);
            });
        });

        // Materias
        Route::apiResource('materias', MateriaController::class);
        Route::post('materias/{materia}/asignar-grupo', [MateriaController::class, 'asignarGrupo']);
        Route::put('materias/{materia}/grupos/{grupoId}', [MateriaController::class, 'actualizarAsignacion']);
        Route::delete('materias/{materia}/grupos/{grupoId}', [MateriaController::class, 'desasignarGrupo']);

        // Ciclos Escolares y Períodos
        Route::apiResource('ciclos-escolares', CicloEscolarController::class);
        Route::post('ciclos-escolares/{cicloEscolar}/periodos/generate', [PeriodoController::class, 'generateBatch']);
        Route::apiResource('periodos', PeriodoController::class);

        // Calificaciones
        Route::apiResource('calificaciones', CalificacionController::class);
        Route::post('calificaciones/batch', [CalificacionController::class, 'storeBatch']);
        Route::get('alumnos/{alumnoId}/boleta', [CalificacionController::class, 'boleta']);

        // Asistencias
        Route::apiResource('asistencias', AsistenciaController::class);
        Route::post('grupos/{grupoId}/asistencias', [AsistenciaController::class, 'registrarGrupo']);
        Route::get('alumnos/{alumnoId}/reporte-asistencias', [AsistenciaController::class, 'reporteAlumno']);

        // Conceptos de Cobro
        Route::apiResource('conceptos-cobro', ConceptoCobroController::class);

        // Cobranza - Plantillas de Planes de Pago
        Route::apiResource('plantillas-plan-pago', PlantillaPlanPagoController::class);

        // Cobranza - Planes de Pago
        Route::post('planes-pago/al-vuelo', [PlanPagoController::class, 'crearAlVuelo']);
        Route::post('planes-pago/from-template', [PlanPagoController::class, 'createFromTemplate']);
        Route::apiResource('planes-pago', PlanPagoController::class);
        Route::post('planes-pago/{id}/duplicate', [PlanPagoController::class, 'duplicate']);
        Route::post('planes-pago/{id}/save-as-template', [PlanPagoController::class, 'saveAsTemplate']);

        // Conceptos de Plan de Pago (nested resource)
        Route::prefix('planes-pago/{planPago}')->group(function () {
            Route::get('conceptos', [ConceptoPlanPagoController::class, 'index']);
            Route::post('conceptos', [ConceptoPlanPagoController::class, 'store']);
            Route::get('conceptos/{concepto}', [ConceptoPlanPagoController::class, 'show']);
            Route::put('conceptos/{concepto}', [ConceptoPlanPagoController::class, 'update']);
            Route::delete('conceptos/{concepto}', [ConceptoPlanPagoController::class, 'destroy']);
        });

        // Finanzas de Alumno
        Route::get('alumnos/{alumno}/finanzas', [FinanzasAlumnoController::class, 'index']);
        Route::get('alumnos/{alumno}/finanzas/{cuenta}', [FinanzasAlumnoController::class, 'show']);

        // Configuración de Escuela
        Route::prefix('escuela')->group(function () {
            Route::get('/', [EscuelaController::class, 'show']);
            Route::put('/', [EscuelaController::class, 'update']);
            Route::get('/configuracion', [EscuelaController::class, 'getConfiguracion']);
            Route::put('/configuracion', [EscuelaController::class, 'updateConfiguracion']);
        });
    });
});
