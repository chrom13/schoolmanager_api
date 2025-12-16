<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsistenciaController extends Controller
{
    /**
     * Lista asistencias con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Asistencia::with(['alumno', 'grupo']);

        // Filtrar por alumno
        if ($request->has('alumno_id')) {
            $query->where('alumno_id', $request->alumno_id);
        }

        // Filtrar por grupo
        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }

        // Filtrar por fecha
        if ($request->has('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        // Filtrar por rango de fechas
        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
        }

        // Filtrar por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $asistencias = $query->orderBy('fecha', 'desc')->get();

        return response()->json([
            'data' => $asistencias
        ]);
    }

    /**
     * Registrar asistencia
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'grupo_id' => ['required', 'exists:grupos,id'],
            'fecha' => ['required', 'date'],
            'estado' => ['required', Rule::in(['presente', 'falta', 'retardo', 'justificada'])],
            'observaciones' => ['nullable', 'string'],
        ]);

        // Verificar si ya existe registro para ese alumno en esa fecha
        $existe = Asistencia::where('alumno_id', $request->alumno_id)
            ->whereDate('fecha', $request->fecha)
            ->first();

        if ($existe) {
            // Actualizar
            $existe->update([
                'estado' => $request->estado,
                'observaciones' => $request->observaciones,
            ]);

            return response()->json([
                'message' => 'Asistencia actualizada exitosamente',
                'data' => $existe->load(['alumno', 'grupo'])
            ]);
        }

        // Crear nueva
        $asistencia = Asistencia::create($request->only([
            'alumno_id',
            'grupo_id',
            'fecha',
            'estado',
            'observaciones'
        ]));

        return response()->json([
            'message' => 'Asistencia registrada exitosamente',
            'data' => $asistencia->load(['alumno', 'grupo'])
        ], 201);
    }

    /**
     * Mostrar asistencia específica
     */
    public function show(Asistencia $asistencia): JsonResponse
    {
        $asistencia->load(['alumno', 'grupo']);

        return response()->json([
            'data' => $asistencia
        ]);
    }

    /**
     * Actualizar asistencia
     */
    public function update(Request $request, Asistencia $asistencia): JsonResponse
    {
        $request->validate([
            'estado' => ['sometimes', Rule::in(['presente', 'falta', 'retardo', 'justificada'])],
            'observaciones' => ['nullable', 'string'],
        ]);

        $asistencia->update($request->only(['estado', 'observaciones']));

        return response()->json([
            'message' => 'Asistencia actualizada exitosamente',
            'data' => $asistencia->load(['alumno', 'grupo'])
        ]);
    }

    /**
     * Eliminar asistencia
     */
    public function destroy(Asistencia $asistencia): JsonResponse
    {
        $asistencia->delete();

        return response()->json([
            'message' => 'Asistencia eliminada exitosamente'
        ]);
    }

    /**
     * Registrar asistencia de todo un grupo
     */
    public function registrarGrupo(Request $request, int $grupoId): JsonResponse
    {
        $request->validate([
            'fecha' => ['required', 'date'],
            'asistencias' => ['required', 'array'],
            'asistencias.*.alumno_id' => ['required', 'exists:alumnos,id'],
            'asistencias.*.estado' => ['required', Rule::in(['presente', 'falta', 'retardo', 'justificada'])],
            'asistencias.*.observaciones' => ['nullable', 'string'],
        ]);

        $grupo = Grupo::findOrFail($grupoId);
        $registradas = [];

        foreach ($request->asistencias as $asistenciaData) {
            $asistencia = Asistencia::updateOrCreate(
                [
                    'alumno_id' => $asistenciaData['alumno_id'],
                    'fecha' => $request->fecha,
                ],
                [
                    'grupo_id' => $grupoId,
                    'estado' => $asistenciaData['estado'],
                    'observaciones' => $asistenciaData['observaciones'] ?? null,
                ]
            );

            $registradas[] = $asistencia;
        }

        return response()->json([
            'message' => 'Asistencias del grupo registradas exitosamente',
            'data' => $registradas
        ]);
    }

    /**
     * Reporte de asistencias de un alumno
     */
    public function reporteAlumno(Request $request, int $alumnoId): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $asistencias = Asistencia::where('alumno_id', $alumnoId)
            ->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin])
            ->get();

        $reporte = [
            'alumno_id' => $alumnoId,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'total' => $asistencias->count(),
            'presentes' => $asistencias->where('estado', 'presente')->count(),
            'faltas' => $asistencias->where('estado', 'falta')->count(),
            'retardos' => $asistencias->where('estado', 'retardo')->count(),
            'justificadas' => $asistencias->where('estado', 'justificada')->count(),
            'porcentaje_asistencia' => $asistencias->count() > 0
                ? round(($asistencias->where('estado', 'presente')->count() / $asistencias->count()) * 100, 2)
                : 0,
        ];

        return response()->json([
            'data' => $reporte
        ]);
    }
}
