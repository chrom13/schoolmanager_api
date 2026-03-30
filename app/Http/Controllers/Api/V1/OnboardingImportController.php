<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CicloEscolar;
use App\Models\ConceptoPlanPago;
use App\Models\Grupo;
use App\Models\ImportSession;
use App\Models\PlanPago;
use App\Services\AlumnoImportParserService;
use App\Services\ColumnMappingService;
use App\Services\ExcelParserService;
use App\Services\ImportacionOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardingImportController extends Controller
{
    public function __construct(
        private readonly ExcelParserService         $excelParser,
        private readonly ColumnMappingService       $columnMapper,
        private readonly AlumnoImportParserService  $importParser,
        private readonly ImportacionOnboardingService $importService,
    ) {}

    // =========================================================================
    // GET /onboarding/import/session-activa
    // =========================================================================

    /**
     * Busca una sesión activa (no terminal) para la escuela del usuario.
     * Retorna null si no existe ninguna.
     */
    public function sessionActiva(): JsonResponse
    {
        $escuelaId = auth()->user()->escuela_id;

        $session = ImportSession::where('escuela_id', $escuelaId)
            ->whereNotIn('status', ImportSession::TERMINAL_STATUSES)
            ->latest()
            ->first();

        return response()->json([
            'data' => $session,
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/upload
    // =========================================================================

    /**
     * Paso 1a: Sube el archivo y devuelve las hojas disponibles.
     * Si ya existe una sesión activa, retorna 409 con el session_id.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $escuelaId = auth()->user()->escuela_id;

        // Verificar sesión activa previa
        $sessionActiva = ImportSession::where('escuela_id', $escuelaId)
            ->whereNotIn('status', ImportSession::TERMINAL_STATUSES)
            ->latest()
            ->first();

        if ($sessionActiva) {
            return response()->json([
                'message'    => 'Ya existe una sesión de importación activa.',
                'session_id' => $sessionActiva->id,
            ], 409);
        }

        $archivo = $request->file('archivo');
        $nombreOriginal = $archivo->getClientOriginalName();
        $extension = $archivo->getClientOriginalExtension();
        $uuid = Str::uuid();
        $rutaRelativa = "imports/{$escuelaId}/{$uuid}.{$extension}";

        // Guardar en storage/app/private/ (el disco 'local' ya tiene root = app/private)
        Storage::disk('local')->putFileAs(
            "imports/{$escuelaId}",
            $archivo,
            "{$uuid}.{$extension}"
        );

        $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);

        // Leer hojas del archivo
        $hojas = $this->excelParser->readSheets($rutaAbsoluta);

        if (empty($hojas)) {
            Storage::disk('local')->delete($rutaRelativa);
            return response()->json([
                'message' => 'No se encontraron hojas con datos en el archivo.',
            ], 422);
        }

        // Crear la sesión
        $session = ImportSession::create([
            'escuela_id'              => $escuelaId,
            'user_id'                 => auth()->id(),
            'archivo_path'            => $rutaRelativa,
            'archivo_nombre_original' => $nombreOriginal,
            'hojas_disponibles'       => $hojas,
            'status'                  => ImportSession::STATUS_PENDING,
        ]);

        return response()->json([
            'message'    => 'Archivo subido exitosamente.',
            'session_id' => $session->id,
            'hojas'      => $hojas,
        ], 201);
    }

    // =========================================================================
    // GET /onboarding/import/{session}
    // =========================================================================

    public function show(ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        return response()->json([
            'data' => $session,
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/hoja
    // =========================================================================

    /**
     * Paso 1b: El usuario confirma qué hoja importar y el ciclo escolar.
     * Si cambian respecto a lo guardado y el status ya avanzó, resetea downstream.
     */
    public function seleccionarHoja(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        $request->validate([
            'hoja_seleccionada' => ['required', 'string'],
            'ciclo_escolar_id'  => ['required', 'exists:ciclos_escolares,id'],
        ]);

        $hojaAnterior  = $session->hoja_seleccionada;
        $cicloAnterior = $session->ciclo_escolar_id;
        $hojaNueva     = $request->hoja_seleccionada;
        $cicloNuevo    = (int) $request->ciclo_escolar_id;

        // Si ya avanzó más allá de pending y hay cambio, resetear downstream
        if ($session->status !== ImportSession::STATUS_PENDING) {
            $hojaDistinta  = $hojaAnterior !== null && $hojaAnterior !== $hojaNueva;
            $cicloDistinto = $cicloAnterior !== null && $cicloAnterior !== $cicloNuevo;

            if ($hojaDistinta || $cicloDistinto) {
                $session->resetDownstream();
            }
        }

        // Actualizar campos de la sesión
        $session->update([
            'hoja_seleccionada' => $hojaNueva,
            'ciclo_escolar_id'  => $cicloNuevo,
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/mapeo
    // =========================================================================

    /**
     * Paso 2: Guarda el mapeo de columnas confirmado por el usuario.
     * Devuelve sugerencias automáticas si no se envía mapeo (para pre-rellenar la UI).
     */
    public function mapeo(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        // GET sin body → devolver sugerencias automáticas
        if (!$request->has('mapeo')) {
            $hoja = $session->hoja_seleccionada;
            $hojas = $session->hojas_disponibles ?? [];
            $columnas = [];
            foreach ($hojas as $h) {
                if ($h['nombre'] === $hoja) {
                    $columnas = $h['columnas'];
                    break;
                }
            }
            $sugerencias = $this->columnMapper->suggest($columnas);
            return response()->json([
                'columnas'    => $columnas,
                'sugerencias' => $sugerencias,
                'campos_obligatorios' => ColumnMappingService::CAMPOS_OBLIGATORIOS,
                'campos_opcionales'   => ColumnMappingService::CAMPOS_OPCIONALES,
            ]);
        }

        $request->validate([
            'mapeo' => ['required', 'array'],
        ]);

        $mapeo = $request->mapeo; // {excel_col => campo_alumno|null}

        // Verificar que los campos obligatorios están mapeados
        $camposMapeados = array_values(array_filter($mapeo));
        $faltantes = array_diff(ColumnMappingService::CAMPOS_OBLIGATORIOS, $camposMapeados);

        if (!empty($faltantes)) {
            return response()->json([
                'message'  => 'Faltan campos obligatorios en el mapeo.',
                'faltantes' => $faltantes,
            ], 422);
        }

        $session->update([
            'mapeo_columnas' => $mapeo,
            'status'         => ImportSession::STATUS_MAPPED,
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/preview
    // =========================================================================

    /**
     * Paso 3: Ejecuta la validación y parseo de todos los alumnos del archivo.
     */
    public function preview(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        if (empty($session->mapeo_columnas)) {
            return response()->json(['message' => 'Primero debes configurar el mapeo de columnas.'], 422);
        }

        $resultado = $this->importParser->parse($session);

        $session->update([
            'alumnos_parseados' => $resultado['alumnos'],
            'grupos_a_crear'    => $resultado['grupos_a_crear'],
            'status'            => ImportSession::STATUS_PREVIEWED,
        ]);

        // Enriquecer grupos_a_crear con los alumnos afectados y grupos existentes del mismo grado
        $alumnosValidos = array_filter($resultado['alumnos'], fn($a) => $a['es_valido']);
        $gradoIds = array_unique(array_column($resultado['grupos_a_crear'], 'grado_id'));

        $gruposExistentesPorGrado = [];
        if (!empty($gradoIds)) {
            $gruposExistentes = Grupo::where('ciclo_escolar_id', $session->ciclo_escolar_id)
                ->whereIn('grado_id', $gradoIds)
                ->get();
            foreach ($gruposExistentes as $g) {
                $gruposExistentesPorGrado[$g->grado_id][] = ['id' => $g->id, 'nombre' => $g->nombre];
            }
        }

        $gruposACrearEnriquecidos = array_map(function ($grupo) use ($alumnosValidos) {
            $afectados = array_values(array_filter($alumnosValidos, fn($a) =>
                $a['grado_id'] === $grupo['grado_id'] && $a['grupo_letra'] === $grupo['grupo_letra']
            ));
            $nombres = array_map(fn($a) => trim(($a['nombre'] ?? '') . ' ' . ($a['apellido_paterno'] ?? '')), $afectados);
            return array_merge($grupo, [
                'total_alumnos'   => count($afectados),
                'alumnos_nombres' => array_values($nombres),
            ]);
        }, $resultado['grupos_a_crear']);

        return response()->json([
            'validos'                    => $resultado['validos'],
            'errores'                    => $resultado['errores'],
            'omitidos'                   => $resultado['omitidos'],
            'grupos_a_crear'             => array_values($gruposACrearEnriquecidos),
            'grupos_existentes_por_grado' => $gruposExistentesPorGrado,
            'preview_alumnos'            => array_values(array_slice($alumnosValidos, 0, 10)),
        ]);
    }

    // =========================================================================
    // GET /onboarding/import/{session}/planes
    // =========================================================================

    /**
     * Paso 4: Devuelve los planes de pago disponibles para el ciclo de la sesión.
     * También incluye los conceptos de todos los planes involucrados (para la conciliación).
     */
    public function planes(ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        if (!$session->ciclo_escolar_id) {
            return response()->json(['message' => 'No hay ciclo escolar seleccionado en la sesión.'], 422);
        }

        $planesDisponibles = PlanPago::with('conceptos')
            ->where('ciclo_escolar_id', $session->ciclo_escolar_id)
            ->where('activo', true)
            ->get();

        // Construir respuesta con plan_general y planes_alternativos separados
        $planGeneral = null;
        $planesAlternativos = [];

        foreach ($planesDisponibles as $plan) {
            $planData = [
                'id'          => $plan->id,
                'nombre'      => $plan->nombre,
                'descripcion' => $plan->descripcion,
                'conceptos'   => $plan->conceptos->map(fn($c) => [
                    'id'                => $c->id,
                    'concepto'          => $c->concepto,
                    'orden'             => $c->orden,
                    'monto_base'        => $c->monto_base,
                    'monto_pronto_pago' => $c->monto_pronto_pago,
                    'monto_recargo'     => $c->monto_recargo,
                    'fecha_vencimiento' => $c->fecha_vencimiento?->format('Y-m-d'),
                ])->values(),
            ];

            if ($plan->id === $session->plan_general_id) {
                $planGeneral = $planData;
            } else {
                $planesAlternativos[] = $planData;
            }
        }

        // Si aún no hay plan_general asignado, simplemente devolver la lista flat
        return response()->json([
            'planes'              => $planesDisponibles->map(fn($p) => [
                'id'          => $p->id,
                'nombre'      => $p->nombre,
                'descripcion' => $p->descripcion,
                'total_conceptos' => $p->conceptos->count(),
                'conceptos'   => $p->conceptos->map(fn($c) => [
                    'id'                => $c->id,
                    'concepto'          => $c->concepto,
                    'orden'             => $c->orden,
                    'monto_base'        => $c->monto_base,
                    'monto_pronto_pago' => $c->monto_pronto_pago,
                    'monto_recargo'     => $c->monto_recargo,
                    'fecha_vencimiento' => $c->fecha_vencimiento?->format('Y-m-d'),
                ])->values(),
            ])->values(),
            'plan_general'        => $planGeneral,
            'planes_alternativos' => array_values($planesAlternativos),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/resolver-grupos
    // =========================================================================

    /**
     * Paso 3b: Resuelve los grupos desconocidos del Excel.
     * Por cada grupo sin match, el usuario decide si se crea nuevo o se reasigna
     * a un grupo existente (corrección de errores tipográficos en el Excel).
     *
     * Body: {
     *   decisiones: [
     *     { grado_id: 5, grupo_letra: "C", accion: "crear" }
     *     { grado_id: 7, grupo_letra: "C", accion: "reasignar", grupo_id_destino: 12 }
     *   ]
     * }
     */
    public function resolverGrupos(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        $request->validate([
            'decisiones'                       => ['required', 'array'],
            'decisiones.*.grado_id'            => ['required', 'integer'],
            'decisiones.*.grupo_letra'         => ['required', 'string'],
            'decisiones.*.accion'              => ['required', 'in:crear,reasignar'],
            'decisiones.*.grupo_id_destino'    => ['nullable', 'integer', 'exists:grupos,id'],
        ]);

        $alumnos = $session->alumnos_parseados ?? [];
        $gruposACrear = $session->grupos_a_crear ?? [];

        foreach ($request->decisiones as $decision) {
            if ($decision['accion'] !== 'reasignar') {
                continue;
            }

            $grupoDestino = Grupo::find($decision['grupo_id_destino']);
            if (!$grupoDestino) {
                continue;
            }

            $gradoIdOrigen    = (int) $decision['grado_id'];
            $grupoLetraOrigen = strtoupper($decision['grupo_letra']);

            // Actualizar alumnos afectados
            $alumnos = array_map(function ($alumno) use ($gradoIdOrigen, $grupoLetraOrigen, $grupoDestino) {
                if (
                    $alumno['grado_id'] === $gradoIdOrigen &&
                    strtoupper($alumno['grupo_letra'] ?? '') === $grupoLetraOrigen
                ) {
                    $alumno['grupo_id']    = $grupoDestino->id;
                    $alumno['grupo_letra'] = strtoupper($grupoDestino->nombre);
                }
                return $alumno;
            }, $alumnos);

            // Quitar el grupo de grupos_a_crear
            $gruposACrear = array_values(array_filter($gruposACrear, fn($g) =>
                !($g['grado_id'] === $gradoIdOrigen && strtoupper($g['grupo_letra']) === $grupoLetraOrigen)
            ));
        }

        $session->update([
            'alumnos_parseados' => $alumnos,
            'grupos_a_crear'    => $gruposACrear,
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/plan-general
    // =========================================================================

    public function planGeneral(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        $request->validate([
            'plan_general_id' => ['required', 'exists:planes_pago,id'],
        ]);

        $session->update([
            'plan_general_id' => $request->plan_general_id,
            'status'          => ImportSession::STATUS_PLAN_ASSIGNED,
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/planes-por-alumno
    // =========================================================================

    /**
     * Paso 5: Guarda los overrides de plan por alumno.
     * Solo los alumnos con plan diferente al general necesitan estar en este array.
     */
    public function planesPorAlumno(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        $request->validate([
            'planes_por_alumno'                  => ['present', 'array'],
            'planes_por_alumno.*.temp_id'        => ['required', 'string'],
            'planes_por_alumno.*.plan_pago_id'   => ['required', 'exists:planes_pago,id'],
        ]);

        $session->update([
            'planes_por_alumno' => $request->planes_por_alumno,
            'status'            => ImportSession::STATUS_PLAN_ASSIGNED,
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/morosos
    // =========================================================================

    /**
     * Paso 6: Guarda los datos de conciliación de adeudos.
     *
     * Body:
     * {
     *   morosos: {
     *     por_alumno: [
     *       { temp_id: "row_2", concepto_plan_pago_ids: [1, 2, 3] }
     *     ]
     *   }
     * }
     */
    public function morosos(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        $request->validate([
            'morosos'                                        => ['required', 'array'],
            'morosos.por_alumno'                             => ['required', 'array'],
            'morosos.por_alumno.*.temp_id'                   => ['required', 'string'],
            'morosos.por_alumno.*.concepto_plan_pago_ids'    => ['required', 'array'],
            'morosos.por_alumno.*.concepto_plan_pago_ids.*'  => ['integer'],
        ]);

        $session->update([
            'morosos' => $request->morosos,
            'status'  => ImportSession::STATUS_CONCILIATED,
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    // =========================================================================
    // POST /onboarding/import/{session}/confirmar
    // =========================================================================

    /**
     * Paso 7: Ejecuta la importación final.
     */
    public function confirmar(Request $request, ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        if ($session->isTerminal()) {
            return response()->json([
                'message' => 'Esta sesión ya fue procesada o cancelada.',
            ], 422);
        }

        if (!$session->plan_general_id) {
            return response()->json([
                'message' => 'Debes asignar un plan de pago general antes de confirmar.',
            ], 422);
        }

        try {
            $resultado = $this->importService->ejecutar($session, auth()->id());

            return response()->json([
                'message'   => 'Importación completada exitosamente.',
                'resultado' => $resultado,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error durante la importación: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // DELETE /onboarding/import/{session}
    // =========================================================================

    /**
     * Cancela la sesión y elimina el archivo temporal.
     */
    public function cancelar(ImportSession $session): JsonResponse
    {
        $this->authorize($session);

        if ($session->status === ImportSession::STATUS_IMPORTED) {
            return response()->json([
                'message' => 'No se puede cancelar una sesión ya importada.',
            ], 422);
        }

        // Eliminar archivo temporal
        if ($session->archivo_path && Storage::disk('local')->exists($session->archivo_path)) {
            Storage::disk('local')->delete($session->archivo_path);
        }

        $session->update(['status' => ImportSession::STATUS_CANCELLED]);

        return response()->json([
            'message' => 'Sesión cancelada exitosamente.',
        ]);
    }

    // =========================================================================
    // Helper de autorización
    // =========================================================================

    private function authorize(ImportSession $session): void
    {
        if ($session->escuela_id !== auth()->user()->escuela_id) {
            abort(403, 'No autorizado.');
        }
    }
}
