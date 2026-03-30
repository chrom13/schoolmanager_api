<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CicloEscolar;
use App\Models\Grado;
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
        $query = Grupo::with(['grado.nivel', 'maestro', 'cicloEscolar']);

        // Filtrar por grado si se proporciona
        if ($request->has('grado_id')) {
            $query->where('grado_id', $request->grado_id);
        }

        // Filtrar por ciclo escolar si se proporciona
        if ($request->has('ciclo_escolar_id')) {
            $query->where('ciclo_escolar_id', $request->ciclo_escolar_id);
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
            'ciclo_escolar_id' => ['required', 'exists:ciclos_escolares,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'capacidad_maxima' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'capacidad_ideal' => ['nullable', 'integer', 'min:1', 'max:100'],
            'maestro_id' => ['nullable', 'exists:users,id'],
        ]);

        // VALIDACIÓN CRÍTICA: Verificar que el ciclo escolar pertenece al mismo nivel que el grado
        $grado = Grado::with('nivel')->findOrFail($request->grado_id);
        $cicloEscolar = CicloEscolar::findOrFail($request->ciclo_escolar_id);

        if ($cicloEscolar->nivel_id !== $grado->nivel_id) {
            return response()->json([
                'message' => 'El ciclo escolar no pertenece al nivel del grado seleccionado',
                'errors' => [
                    'ciclo_escolar_id' => [
                        'El ciclo escolar debe pertenecer al mismo nivel que el grado. ' .
                        'Grado: ' . $grado->nivel->nombre . ', ' .
                        'Ciclo: ' . ($cicloEscolar->nivel ? $cicloEscolar->nivel->nombre : 'sin nivel')
                    ]
                ]
            ], 422);
        }

        $grupo = Grupo::create($request->only(['grado_id', 'ciclo_escolar_id', 'nombre', 'capacidad_maxima', 'capacidad_ideal', 'maestro_id']));

        return response()->json([
            'message' => 'Grupo creado exitosamente',
            'data' => $grupo->load(['grado', 'cicloEscolar', 'maestro'])
        ], 201);
    }

    /**
     * Mostrar un grupo específico
     */
    public function show(Grupo $grupo): JsonResponse
    {
        $grupo->load(['grado.nivel', 'maestro', 'cicloEscolar']);

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
            'capacidad_ideal' => ['nullable', 'integer', 'min:1', 'max:100'],
            'maestro_id' => ['nullable', 'exists:users,id'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $grupo->update($request->only(['nombre', 'capacidad_maxima', 'capacidad_ideal', 'maestro_id', 'activo']));

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

    /**
     * Obtener materias asignadas a un grupo
     */
    public function materias(Grupo $grupo): JsonResponse
    {
        $materias = $grupo->materias()->get();

        return response()->json([
            'data' => $materias
        ]);
    }

    /**
     * Obtener alumnos inscritos en un grupo
     */
    public function alumnos(Grupo $grupo): JsonResponse
    {
        $alumnos = $grupo->alumnos()
            ->wherePivot('estado', 'activa')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $alumnos
        ]);
    }
}
