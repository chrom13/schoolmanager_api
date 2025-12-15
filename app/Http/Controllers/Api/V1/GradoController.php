<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Grado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradoController extends Controller
{
    /**
     * Lista todos los grados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Grado::with(['nivel', 'grupos']);

        // Filtrar por nivel si se proporciona
        if ($request->has('nivel_id')) {
            $query->where('nivel_id', $request->nivel_id);
        }

        $grados = $query->orderBy('orden')->get();

        return response()->json([
            'data' => $grados
        ]);
    }

    /**
     * Crear nuevo grado
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nivel_id' => ['required', 'exists:niveles,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'orden' => ['required', 'integer', 'min:1'],
        ]);

        $grado = Grado::create($request->only(['nivel_id', 'nombre', 'orden']));

        return response()->json([
            'message' => 'Grado creado exitosamente',
            'data' => $grado->load('nivel')
        ], 201);
    }

    /**
     * Mostrar un grado específico
     */
    public function show(Grado $grado): JsonResponse
    {
        $grado->load(['nivel', 'grupos']);

        return response()->json([
            'data' => $grado
        ]);
    }

    /**
     * Actualizar grado
     */
    public function update(Request $request, Grado $grado): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'orden' => ['sometimes', 'integer', 'min:1'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $grado->update($request->only(['nombre', 'orden', 'activo']));

        return response()->json([
            'message' => 'Grado actualizado exitosamente',
            'data' => $grado
        ]);
    }

    /**
     * Eliminar grado
     */
    public function destroy(Grado $grado): JsonResponse
    {
        $grado->delete();

        return response()->json([
            'message' => 'Grado eliminado exitosamente'
        ]);
    }
}
