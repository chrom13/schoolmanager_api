<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CicloEscolar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CicloEscolarController extends Controller
{
    /**
     * Lista todos los ciclos escolares
     */
    public function index(Request $request): JsonResponse
    {
        $query = CicloEscolar::query();

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Incluir períodos si se solicita
        if ($request->boolean('con_periodos')) {
            $query->with('periodos');
        }

        $ciclos = $query->orderBy('fecha_inicio', 'desc')->get();

        return response()->json([
            'data' => $ciclos
        ]);
    }

    /**
     * Crear nuevo ciclo escolar
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
        ]);

        // Validar nombre único por escuela
        $exists = CicloEscolar::where('nombre', $request->nombre)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Ya existe un ciclo escolar con ese nombre',
                'errors' => ['nombre' => ['El nombre ya está en uso']]
            ], 422);
        }

        // Validar que no haya solapamiento de fechas con ciclos activos
        $solapamiento = CicloEscolar::where('activo', true)
            ->where(function ($query) use ($request) {
                $query->whereBetween('fecha_inicio', [$request->fecha_inicio, $request->fecha_fin])
                    ->orWhereBetween('fecha_fin', [$request->fecha_inicio, $request->fecha_fin])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('fecha_inicio', '<=', $request->fecha_inicio)
                          ->where('fecha_fin', '>=', $request->fecha_fin);
                    });
            })
            ->exists();

        if ($solapamiento) {
            return response()->json([
                'message' => 'Las fechas se solapan con otro ciclo escolar activo',
                'errors' => ['fecha_inicio' => ['Conflicto de fechas con otro ciclo activo']]
            ], 422);
        }

        $ciclo = CicloEscolar::create($request->only([
            'nombre',
            'fecha_inicio',
            'fecha_fin'
        ]));

        return response()->json([
            'message' => 'Ciclo escolar creado exitosamente',
            'data' => $ciclo
        ], 201);
    }

    /**
     * Mostrar un ciclo escolar específico
     */
    public function show(CicloEscolar $cicloEscolar): JsonResponse
    {
        $cicloEscolar->load('periodos');

        return response()->json([
            'data' => $cicloEscolar
        ]);
    }

    /**
     * Actualizar ciclo escolar
     */
    public function update(Request $request, CicloEscolar $cicloEscolar): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'date', 'after:fecha_inicio'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // Validar nombre único por escuela (excluyendo el registro actual)
        if ($request->has('nombre') && $request->nombre !== $cicloEscolar->nombre) {
            $exists = CicloEscolar::where('nombre', $request->nombre)->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un ciclo escolar con ese nombre',
                    'errors' => ['nombre' => ['El nombre ya está en uso']]
                ], 422);
            }
        }

        // Validar solapamiento si se actualizan fechas
        if ($request->has('fecha_inicio') || $request->has('fecha_fin')) {
            $fechaInicio = $request->fecha_inicio ?? $cicloEscolar->fecha_inicio;
            $fechaFin = $request->fecha_fin ?? $cicloEscolar->fecha_fin;

            $solapamiento = CicloEscolar::where('id', '!=', $cicloEscolar->id)
                ->where('activo', true)
                ->where(function ($query) use ($fechaInicio, $fechaFin) {
                    $query->whereBetween('fecha_inicio', [$fechaInicio, $fechaFin])
                        ->orWhereBetween('fecha_fin', [$fechaInicio, $fechaFin])
                        ->orWhere(function ($q) use ($fechaInicio, $fechaFin) {
                            $q->where('fecha_inicio', '<=', $fechaInicio)
                              ->where('fecha_fin', '>=', $fechaFin);
                        });
                })
                ->exists();

            if ($solapamiento) {
                return response()->json([
                    'message' => 'Las fechas se solapan con otro ciclo escolar activo',
                    'errors' => ['fecha_inicio' => ['Conflicto de fechas con otro ciclo activo']]
                ], 422);
            }
        }

        $cicloEscolar->update($request->only([
            'nombre',
            'fecha_inicio',
            'fecha_fin',
            'activo'
        ]));

        return response()->json([
            'message' => 'Ciclo escolar actualizado exitosamente',
            'data' => $cicloEscolar
        ]);
    }

    /**
     * Eliminar ciclo escolar
     */
    public function destroy(CicloEscolar $cicloEscolar): JsonResponse
    {
        $cicloEscolar->delete();

        return response()->json([
            'message' => 'Ciclo escolar eliminado exitosamente'
        ]);
    }
}
