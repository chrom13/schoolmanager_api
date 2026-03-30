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

        // Siempre cargar relación con nivel y periodos
        $query->with(['nivel', 'periodos']);

        // Filtrar por nivel
        if ($request->has('nivel_id')) {
            $query->where('nivel_id', $request->nivel_id);
        }

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
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
            'nivel_id' => ['required', 'exists:niveles,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
        ]);

        // Validar nombre único POR NIVEL (no globalmente)
        $exists = CicloEscolar::where('nivel_id', $request->nivel_id)
            ->where('nombre', $request->nombre)
            ->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Ya existe un ciclo escolar con ese nombre para este nivel',
                'errors' => ['nombre' => ['El nombre ya está en uso para este nivel']]
            ], 422);
        }

        // Validar que no haya solapamiento de fechas SOLO dentro del mismo nivel
        $solapamiento = CicloEscolar::where('nivel_id', $request->nivel_id)
            ->where('activo', true)
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
                'message' => 'Las fechas se solapan con otro ciclo escolar activo del mismo nivel',
                'errors' => ['fecha_inicio' => ['Conflicto de fechas con otro ciclo activo del mismo nivel']]
            ], 422);
        }

        $ciclo = CicloEscolar::create($request->only([
            'nivel_id',
            'nombre',
            'fecha_inicio',
            'fecha_fin'
        ]));

        // Cargar relaciones para la respuesta
        $ciclo->load(['nivel', 'periodos']);

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
        $cicloEscolar->load(['nivel', 'periodos']);

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
            'nivel_id' => ['sometimes', 'exists:niveles,id'],
            'nombre' => ['sometimes', 'string', 'max:255'],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'date', 'after:fecha_inicio'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // NO permitir cambiar nivel_id si hay grupos o inscripciones asociadas
        if ($request->has('nivel_id') && $request->nivel_id != $cicloEscolar->nivel_id) {
            if ($cicloEscolar->grupos()->exists() || $cicloEscolar->inscripciones()->exists()) {
                return response()->json([
                    'message' => 'No se puede cambiar el nivel de un ciclo escolar que ya tiene grupos o inscripciones asociadas',
                    'errors' => ['nivel_id' => ['No se puede cambiar el nivel con datos asociados']]
                ], 422);
            }
        }

        $nivelId = $request->nivel_id ?? $cicloEscolar->nivel_id;

        // Validar nombre único POR NIVEL (excluyendo el registro actual)
        if ($request->has('nombre') && $request->nombre !== $cicloEscolar->nombre) {
            $exists = CicloEscolar::where('id', '!=', $cicloEscolar->id)
                ->where('nivel_id', $nivelId)
                ->where('nombre', $request->nombre)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un ciclo escolar con ese nombre para este nivel',
                    'errors' => ['nombre' => ['El nombre ya está en uso para este nivel']]
                ], 422);
            }
        }

        // Validar solapamiento SOLO dentro del mismo nivel
        if ($request->has('fecha_inicio') || $request->has('fecha_fin')) {
            $fechaInicio = $request->fecha_inicio ?? $cicloEscolar->fecha_inicio;
            $fechaFin = $request->fecha_fin ?? $cicloEscolar->fecha_fin;

            $solapamiento = CicloEscolar::where('id', '!=', $cicloEscolar->id)
                ->where('nivel_id', $nivelId)
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
                    'message' => 'Las fechas se solapan con otro ciclo escolar activo del mismo nivel',
                    'errors' => ['fecha_inicio' => ['Conflicto de fechas con otro ciclo activo del mismo nivel']]
                ], 422);
            }
        }

        $cicloEscolar->update($request->only([
            'nivel_id',
            'nombre',
            'fecha_inicio',
            'fecha_fin',
            'activo'
        ]));

        // Cargar relaciones para la respuesta
        $cicloEscolar->load(['nivel', 'periodos']);

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
