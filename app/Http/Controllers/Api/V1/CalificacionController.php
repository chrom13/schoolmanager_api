<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalificacionController extends Controller
{
    /**
     * Lista calificaciones con filtros opcionales
     */
    public function index(Request $request): JsonResponse
    {
        $query = Calificacion::with(['alumno', 'materia', 'periodo', 'maestro']);

        // Filtrar por alumno
        if ($request->has('alumno_id')) {
            $query->where('alumno_id', $request->alumno_id);
        }

        // Filtrar por materia
        if ($request->has('materia_id')) {
            $query->where('materia_id', $request->materia_id);
        }

        // Filtrar por período
        if ($request->has('periodo_id')) {
            $query->where('periodo_id', $request->periodo_id);
        }

        $calificaciones = $query->get();

        return response()->json([
            'data' => $calificaciones
        ]);
    }

    /**
     * Crear o actualizar calificación
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'alumno_id' => ['required', 'exists:alumnos,id'],
            'materia_id' => ['required', 'exists:materias,id'],
            'periodo_id' => ['required', 'exists:periodos,id'],
            'calificacion' => ['required', 'numeric', 'min:0', 'max:100'],
            'observaciones' => ['nullable', 'string'],
            'maestro_id' => ['nullable', 'exists:usuarios,id'],
        ]);

        // Verificar si ya existe una calificación
        $calificacion = Calificacion::where('alumno_id', $request->alumno_id)
            ->where('materia_id', $request->materia_id)
            ->where('periodo_id', $request->periodo_id)
            ->first();

        if ($calificacion) {
            // Actualizar
            $calificacion->update([
                'calificacion' => $request->calificacion,
                'observaciones' => $request->observaciones,
                'maestro_id' => $request->maestro_id ?? auth()->id(),
            ]);

            return response()->json([
                'message' => 'Calificación actualizada exitosamente',
                'data' => $calificacion->load(['alumno', 'materia', 'periodo'])
            ]);
        }

        // Crear nueva
        $calificacion = Calificacion::create([
            'alumno_id' => $request->alumno_id,
            'materia_id' => $request->materia_id,
            'periodo_id' => $request->periodo_id,
            'calificacion' => $request->calificacion,
            'observaciones' => $request->observaciones,
            'maestro_id' => $request->maestro_id ?? auth()->id(),
        ]);

        return response()->json([
            'message' => 'Calificación creada exitosamente',
            'data' => $calificacion->load(['alumno', 'materia', 'periodo'])
        ], 201);
    }

    /**
     * Mostrar calificación específica
     */
    public function show(Calificacion $calificacion): JsonResponse
    {
        $calificacion->load(['alumno', 'materia', 'periodo', 'maestro']);

        return response()->json([
            'data' => $calificacion
        ]);
    }

    /**
     * Actualizar calificación
     */
    public function update(Request $request, Calificacion $calificacion): JsonResponse
    {
        $request->validate([
            'calificacion' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $calificacion->update($request->only(['calificacion', 'observaciones']));

        return response()->json([
            'message' => 'Calificación actualizada exitosamente',
            'data' => $calificacion->load(['alumno', 'materia', 'periodo'])
        ]);
    }

    /**
     * Eliminar calificación
     */
    public function destroy(Calificacion $calificacion): JsonResponse
    {
        $calificacion->delete();

        return response()->json([
            'message' => 'Calificación eliminada exitosamente'
        ]);
    }

    /**
     * Obtener boleta de calificaciones de un alumno
     */
    public function boleta(Request $request, int $alumnoId): JsonResponse
    {
        $request->validate([
            'periodo_id' => ['required', 'exists:periodos,id'],
        ]);

        $calificaciones = Calificacion::with(['materia', 'periodo', 'maestro'])
            ->where('alumno_id', $alumnoId)
            ->where('periodo_id', $request->periodo_id)
            ->get();

        return response()->json([
            'data' => [
                'alumno_id' => $alumnoId,
                'periodo_id' => $request->periodo_id,
                'calificaciones' => $calificaciones,
                'promedio' => $calificaciones->avg('calificacion')
            ]
        ]);
    }
}
