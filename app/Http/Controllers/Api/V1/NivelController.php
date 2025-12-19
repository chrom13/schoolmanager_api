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
        $niveles = Nivel::with('grados')->get();

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
                Rule::unique('niveles', 'nombre')->where('escuela_id', $escuelaId)
            ],
        ], [
            'nombre.unique' => 'Este nivel ya existe en tu escuela',
        ]);

        $nivel = Nivel::create([
            'nombre' => $request->nombre,
            'activo' => true,
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
            'activo' => ['sometimes', 'boolean'],
        ]);

        $nivel->update($request->only(['nombre', 'activo']));

        return response()->json([
            'message' => 'Nivel actualizado exitosamente',
            'data' => $nivel
        ]);
    }

    /**
     * Eliminar nivel
     */
    public function destroy(Nivel $nivel): JsonResponse
    {
        // Verificar si el nivel tiene grados asociados
        if ($nivel->grados()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el nivel porque tiene grados asociados'
            ], 422);
        }

        $nivel->delete();

        return response()->json([
            'message' => 'Nivel eliminado exitosamente'
        ]);
    }
}
