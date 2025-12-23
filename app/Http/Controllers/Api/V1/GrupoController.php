<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    /**
     * Lista todos los grupos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Grupo::with(['grado.nivel', 'maestro']);

        // Filtrar por grado si se proporciona
        if ($request->has('grado_id')) {
            $query->where('grado_id', $request->grado_id);
        }

        $grupos = $query->get();

        return response()->json([
            'data' => $grupos
        ]);
    }

    /**
     * Crear nuevo grupo
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'grado_id' => ['required', 'exists:grados,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'capacidad_maxima' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'maestro_id' => ['nullable', 'exists:users,id'],
        ]);

        $grupo = Grupo::create($request->only(['grado_id', 'nombre', 'capacidad_maxima', 'maestro_id']));

        return response()->json([
            'message' => 'Grupo creado exitosamente',
            'data' => $grupo->load(['grado', 'maestro'])
        ], 201);
    }

    /**
     * Mostrar un grupo específico
     */
    public function show(Grupo $grupo): JsonResponse
    {
        $grupo->load(['grado.nivel', 'maestro']);

        return response()->json([
            'data' => $grupo
        ]);
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, Grupo $grupo): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'capacidad_maxima' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'maestro_id' => ['nullable', 'exists:users,id'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $grupo->update($request->only(['nombre', 'capacidad_maxima', 'maestro_id', 'activo']));

        return response()->json([
            'message' => 'Grupo actualizado exitosamente',
            'data' => $grupo
        ]);
    }

    /**
     * Eliminar grupo
     */
    public function destroy(Grupo $grupo): JsonResponse
    {
        $grupo->delete();

        return response()->json([
            'message' => 'Grupo eliminado exitosamente'
        ]);
    }
}
