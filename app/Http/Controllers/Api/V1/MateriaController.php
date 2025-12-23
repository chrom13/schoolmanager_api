<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Materia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MateriaController extends Controller
{
    /**
     * Lista todas las materias
     */
    public function index(Request $request): JsonResponse
    {
        $query = Materia::query();

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Incluir grupos si se solicita
        if ($request->boolean('con_grupos')) {
            $query->with(['grupos.grado.nivel']);
        }

        $materias = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => $materias
        ]);
    }

    /**
     * Crear nueva materia
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'clave' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Validar nombre único por escuela
        $exists = Materia::where('nombre', $request->nombre)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una materia con ese nombre',
                'errors' => ['nombre' => ['El nombre ya está en uso']]
            ], 422);
        }

        $materia = Materia::create($request->only([
            'nombre',
            'clave',
            'descripcion',
            'color'
        ]));

        return response()->json([
            'message' => 'Materia creada exitosamente',
            'data' => $materia
        ], 201);
    }

    /**
     * Mostrar una materia específica
     */
    public function show(Materia $materia): JsonResponse
    {
        $materia->load(['grupos.grado.nivel', 'grupos.maestro']);

        return response()->json([
            'data' => $materia
        ]);
    }

    /**
     * Actualizar materia
     */
    public function update(Request $request, Materia $materia): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'clave' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // Validar nombre único por escuela (excluyendo el registro actual)
        if ($request->has('nombre') && $request->nombre !== $materia->nombre) {
            $exists = Materia::where('nombre', $request->nombre)->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe una materia con ese nombre',
                    'errors' => ['nombre' => ['El nombre ya está en uso']]
                ], 422);
            }
        }

        $materia->update($request->only([
            'nombre',
            'clave',
            'descripcion',
            'color',
            'activo'
        ]));

        return response()->json([
            'message' => 'Materia actualizada exitosamente',
            'data' => $materia
        ]);
    }

    /**
     * Eliminar materia
     */
    public function destroy(Materia $materia): JsonResponse
    {
        $materia->delete();

        return response()->json([
            'message' => 'Materia eliminada exitosamente'
        ]);
    }

    /**
     * Asignar materia a un grupo
     */
    public function asignarGrupo(Request $request, Materia $materia): JsonResponse
    {
        $request->validate([
            'grupo_id' => ['required', 'exists:grupos,id'],
            'maestro_id' => ['nullable', 'exists:users,id'],
            'horas_semanales' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Verificar si ya está asignada
        if ($materia->grupos()->where('grupo_id', $request->grupo_id)->exists()) {
            return response()->json([
                'message' => 'Esta materia ya está asignada a este grupo',
                'errors' => ['grupo_id' => ['La materia ya está asignada a este grupo']]
            ], 422);
        }

        $materia->grupos()->attach($request->grupo_id, [
            'maestro_id' => $request->maestro_id,
            'horas_semanales' => $request->horas_semanales,
            'activo' => true,
        ]);

        return response()->json([
            'message' => 'Materia asignada al grupo exitosamente',
            'data' => $materia->load(['grupos.grado.nivel'])
        ]);
    }

    /**
     * Actualizar asignación de materia a grupo
     */
    public function actualizarAsignacion(Request $request, Materia $materia, int $grupoId): JsonResponse
    {
        $request->validate([
            'maestro_id' => ['nullable', 'exists:users,id'],
            'horas_semanales' => ['nullable', 'integer', 'min:1', 'max:50'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // Verificar que la asignación existe
        if (!$materia->grupos()->where('grupo_id', $grupoId)->exists()) {
            return response()->json([
                'message' => 'Esta materia no está asignada a este grupo',
                'errors' => ['grupo_id' => ['La asignación no existe']]
            ], 404);
        }

        $materia->grupos()->updateExistingPivot($grupoId, array_filter([
            'maestro_id' => $request->maestro_id,
            'horas_semanales' => $request->horas_semanales,
            'activo' => $request->activo,
        ]));

        return response()->json([
            'message' => 'Asignación actualizada exitosamente',
            'data' => $materia->load(['grupos.grado.nivel'])
        ]);
    }

    /**
     * Desasignar materia de un grupo
     */
    public function desasignarGrupo(Materia $materia, int $grupoId): JsonResponse
    {
        $materia->grupos()->detach($grupoId);

        return response()->json([
            'message' => 'Materia desasignada del grupo exitosamente'
        ]);
    }
}
