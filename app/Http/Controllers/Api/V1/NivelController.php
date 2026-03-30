<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NivelEnum;
use App\Http\Controllers\Controller;
use App\Models\Nivel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NivelController extends Controller
{
    /**
     * Lista todos los niveles de la escuela
     */
    public function index(): JsonResponse
    {
        $escuelaId = request()->user()->escuela_id;

        $niveles = Nivel::with('grados')
            ->withCount([
                'grados as total_alumnos' => function ($query) use ($escuelaId) {
                    $query->withoutGlobalScopes()
                        ->join('grupos', 'grados.id', '=', 'grupos.grado_id')
                        ->join('inscripciones', 'grupos.id', '=', 'inscripciones.grupo_id')
                        ->where('grados.escuela_id', $escuelaId)
                        ->where('grupos.escuela_id', $escuelaId)
                        ->where('inscripciones.estado', 'activa')
                        ->whereNull('grupos.deleted_at')
                        ->whereNull('inscripciones.deleted_at');
                }
            ])
            ->get();

        return response()->json([
            'data' => $niveles
        ]);
    }

    /**
     * Crear nuevo nivel
     */
    public function store(Request $request): JsonResponse
    {
        $escuelaId = $request->user()->escuela_id;

        $request->validate([
            'nombre' => [
                'required',
                Rule::in(NivelEnum::values()),
                Rule::unique('niveles', 'nombre')
                    ->where('escuela_id', $escuelaId)
                    ->whereNull('deleted_at')
            ],
        ], [
            'nombre.unique' => 'Este nivel ya existe en tu escuela',
        ]);

        // Verificar si existe un nivel soft-deleted con el mismo nombre
        $nivelEliminado = Nivel::withTrashed()
            ->where('nombre', $request->nombre)
            ->where('escuela_id', $escuelaId)
            ->whereNotNull('deleted_at')
            ->first();

        if ($nivelEliminado) {
            // Restaurar el nivel eliminado
            $nivelEliminado->restore();

            return response()->json([
                'message' => 'Nivel restaurado exitosamente',
                'data' => $nivelEliminado
            ], 200);
        }

        $nivel = Nivel::create([
            'nombre' => $request->nombre,
        ]);

        return response()->json([
            'message' => 'Nivel creado exitosamente',
            'data' => $nivel
        ], 201);
    }

    /**
     * Mostrar un nivel específico
     */
    public function show(Nivel $nivel): JsonResponse
    {
        $nivel->load('grados');

        return response()->json([
            'data' => $nivel
        ]);
    }

    /**
     * Actualizar nivel
     */
    public function update(Request $request, Nivel $nivel): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', Rule::in(NivelEnum::values())],
        ]);

        $nivel->update($request->only(['nombre']));

        return response()->json([
            'message' => 'Nivel actualizado exitosamente',
            'data' => $nivel
        ]);
    }

    /**
     * Eliminar nivel (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        $nivel = Nivel::findOrFail($id);

        // Verificar si el nivel tiene grados activos asociados
        $gradosActivos = $nivel->grados()->count();

        if ($gradosActivos > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el nivel porque tiene grados asociados activos'
            ], 422);
        }

        // Realizar soft delete
        $nivel->delete();

        return response()->json([
            'message' => 'Nivel eliminado exitosamente'
        ], 200);
    }
}
