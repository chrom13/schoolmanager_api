<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConceptoCobro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConceptoCobroController extends Controller
{
    /**
     * Lista conceptos de cobro
     */
    public function index(Request $request): JsonResponse
    {
        $query = ConceptoCobro::with(['nivel', 'grado']);

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Filtrar por nivel
        if ($request->has('nivel_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('nivel_id', $request->nivel_id)
                  ->orWhereNull('nivel_id');
            });
        }

        // Filtrar por grado
        if ($request->has('grado_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('grado_id', $request->grado_id)
                  ->orWhereNull('grado_id');
            });
        }

        // Filtrar por periodicidad
        if ($request->has('periodicidad')) {
            $query->where('periodicidad', $request->periodicidad);
        }

        $conceptos = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => $conceptos
        ]);
    }

    /**
     * Crear concepto de cobro
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'precio_base' => ['required', 'numeric', 'min:0'],
            'periodicidad' => ['required', Rule::in(['unico', 'mensual', 'bimestral', 'trimestral', 'cuatrimestral', 'semestral', 'anual'])],
            'nivel_id' => ['nullable', 'exists:niveles,id'],
            'grado_id' => ['nullable', 'exists:grados,id'],
        ]);

        $concepto = ConceptoCobro::create($request->only([
            'nombre',
            'descripcion',
            'precio_base',
            'periodicidad',
            'nivel_id',
            'grado_id'
        ]));

        return response()->json([
            'message' => 'Concepto de cobro creado exitosamente',
            'data' => $concepto->load(['nivel', 'grado'])
        ], 201);
    }

    /**
     * Mostrar concepto específico
     */
    public function show(ConceptoCobro $conceptoCobro): JsonResponse
    {
        $conceptoCobro->load(['nivel', 'grado']);

        return response()->json([
            'data' => $conceptoCobro
        ]);
    }

    /**
     * Actualizar concepto
     */
    public function update(Request $request, ConceptoCobro $conceptoCobro): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'precio_base' => ['sometimes', 'numeric', 'min:0'],
            'periodicidad' => ['sometimes', Rule::in(['unico', 'mensual', 'bimestral', 'trimestral', 'cuatrimestral', 'semestral', 'anual'])],
            'nivel_id' => ['nullable', 'exists:niveles,id'],
            'grado_id' => ['nullable', 'exists:grados,id'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $conceptoCobro->update($request->only([
            'nombre',
            'descripcion',
            'precio_base',
            'periodicidad',
            'nivel_id',
            'grado_id',
            'activo'
        ]));

        return response()->json([
            'message' => 'Concepto de cobro actualizado exitosamente',
            'data' => $conceptoCobro->load(['nivel', 'grado'])
        ]);
    }

    /**
     * Eliminar concepto
     */
    public function destroy(ConceptoCobro $conceptoCobro): JsonResponse
    {
        $conceptoCobro->delete();

        return response()->json([
            'message' => 'Concepto de cobro eliminado exitosamente'
        ]);
    }
}
