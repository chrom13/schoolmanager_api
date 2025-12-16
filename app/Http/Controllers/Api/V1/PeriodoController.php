<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CicloEscolar;
use App\Models\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodoController extends Controller
{
    /**
     * Lista todos los períodos (opcionalmente filtrados por ciclo)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Periodo::with('cicloEscolar');

        // Filtrar por ciclo escolar si se proporciona
        if ($request->has('ciclo_escolar_id')) {
            $query->where('ciclo_escolar_id', $request->ciclo_escolar_id);
        }

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $periodos = $query->orderBy('fecha_inicio')->get();

        return response()->json([
            'data' => $periodos
        ]);
    }

    /**
     * Crear nuevo período
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ciclo_escolar_id' => ['required', 'exists:ciclos_escolares,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', Rule::in(['bimestre', 'trimestre', 'cuatrimestre', 'semestre', 'anual'])],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
        ]);

        // Verificar que el ciclo escolar pertenece a la escuela del usuario
        $ciclo = CicloEscolar::find($request->ciclo_escolar_id);

        // Validar que las fechas estén dentro del ciclo escolar
        if ($request->fecha_inicio < $ciclo->fecha_inicio || $request->fecha_fin > $ciclo->fecha_fin) {
            return response()->json([
                'message' => 'Las fechas del período deben estar dentro del ciclo escolar',
                'errors' => ['fecha_inicio' => ['Las fechas exceden el rango del ciclo escolar']]
            ], 422);
        }

        // Validar número único por ciclo
        $exists = Periodo::where('ciclo_escolar_id', $request->ciclo_escolar_id)
            ->where('numero', $request->numero)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe un período con ese número en este ciclo',
                'errors' => ['numero' => ['El número ya está en uso en este ciclo']]
            ], 422);
        }

        // Validar que no haya solapamiento de fechas en el mismo ciclo
        $solapamiento = Periodo::where('ciclo_escolar_id', $request->ciclo_escolar_id)
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
                'message' => 'Las fechas se solapan con otro período',
                'errors' => ['fecha_inicio' => ['Conflicto de fechas con otro período']]
            ], 422);
        }

        $periodo = Periodo::create($request->only([
            'ciclo_escolar_id',
            'nombre',
            'numero',
            'tipo',
            'fecha_inicio',
            'fecha_fin'
        ]));

        return response()->json([
            'message' => 'Período creado exitosamente',
            'data' => $periodo->load('cicloEscolar')
        ], 201);
    }

    /**
     * Mostrar un período específico
     */
    public function show(Periodo $periodo): JsonResponse
    {
        $periodo->load('cicloEscolar');

        return response()->json([
            'data' => $periodo
        ]);
    }

    /**
     * Actualizar período
     */
    public function update(Request $request, Periodo $periodo): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'numero' => ['sometimes', 'integer', 'min:1'],
            'tipo' => ['sometimes', Rule::in(['bimestre', 'trimestre', 'cuatrimestre', 'semestre', 'anual'])],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'date', 'after:fecha_inicio'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $ciclo = $periodo->cicloEscolar;

        // Validar fechas dentro del ciclo si se actualizan
        if ($request->has('fecha_inicio') || $request->has('fecha_fin')) {
            $fechaInicio = $request->fecha_inicio ?? $periodo->fecha_inicio;
            $fechaFin = $request->fecha_fin ?? $periodo->fecha_fin;

            if ($fechaInicio < $ciclo->fecha_inicio || $fechaFin > $ciclo->fecha_fin) {
                return response()->json([
                    'message' => 'Las fechas del período deben estar dentro del ciclo escolar',
                    'errors' => ['fecha_inicio' => ['Las fechas exceden el rango del ciclo escolar']]
                ], 422);
            }

            // Validar solapamiento
            $solapamiento = Periodo::where('ciclo_escolar_id', $periodo->ciclo_escolar_id)
                ->where('id', '!=', $periodo->id)
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
                    'message' => 'Las fechas se solapan con otro período',
                    'errors' => ['fecha_inicio' => ['Conflicto de fechas con otro período']]
                ], 422);
            }
        }

        // Validar número único si se actualiza
        if ($request->has('numero') && $request->numero != $periodo->numero) {
            $exists = Periodo::where('ciclo_escolar_id', $periodo->ciclo_escolar_id)
                ->where('numero', $request->numero)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe un período con ese número en este ciclo',
                    'errors' => ['numero' => ['El número ya está en uso en este ciclo']]
                ], 422);
            }
        }

        $periodo->update($request->only([
            'nombre',
            'numero',
            'tipo',
            'fecha_inicio',
            'fecha_fin',
            'activo'
        ]));

        return response()->json([
            'message' => 'Período actualizado exitosamente',
            'data' => $periodo->load('cicloEscolar')
        ]);
    }

    /**
     * Eliminar período
     */
    public function destroy(Periodo $periodo): JsonResponse
    {
        $periodo->delete();

        return response()->json([
            'message' => 'Período eliminado exitosamente'
        ]);
    }
}
