<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use App\Models\CicloEscolar;
use App\Models\Periodo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PeriodoController extends Controller
{
    /**
     * Lista todos los períodos (opcionalmente filtrados por ciclo)
     */
    public function index(Request $request): JsonResponse
    {
        // Scope a través de cicloEscolar — BelongsToTenant filtra por escuela_id del usuario
        $query = Periodo::whereHas('cicloEscolar')->with('cicloEscolar');

        if ($request->has('ciclo_escolar_id')) {
            $query->where('ciclo_escolar_id', $request->ciclo_escolar_id);
        }

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

        // findOrFail con BelongsToTenant devuelve 404 si el ciclo pertenece a otro tenant
        $ciclo = CicloEscolar::findOrFail($request->ciclo_escolar_id);

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
        $this->authorizeTenant($periodo);
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
        $this->authorizeTenant($periodo);

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
        $this->authorizeTenant($periodo);

        // Verificar si el período tiene calificaciones
        $tieneCalificaciones = Calificacion::where('periodo_id', $periodo->id)->exists();

        if ($tieneCalificaciones) {
            return response()->json([
                'message' => 'No se puede eliminar el período porque tiene calificaciones registradas',
                'errors' => ['periodo' => ['Este período tiene calificaciones y no puede ser eliminado']]
            ], 422);
        }

        $periodo->delete();

        return response()->json([
            'message' => 'Período eliminado exitosamente'
        ]);
    }

    private function authorizeTenant(Periodo $periodo): void
    {
        // CicloEscolar tiene BelongsToTenant; si escuela_id no coincide, acceso no autorizado
        $ciclo = $periodo->cicloEscolar;
        if (!$ciclo || $ciclo->escuela_id !== auth()->user()->escuela_id) {
            abort(403, 'No autorizado');
        }
    }

    /**
     * Generar períodos automáticamente para un ciclo escolar
     */
    public function generateBatch(Request $request, CicloEscolar $cicloEscolar): JsonResponse
    {
        $request->validate([
            'tipo' => ['required', Rule::in(['bimestre', 'trimestre', 'cuatrimestre', 'semestre', 'anual'])],
        ]);

        // Verificar si ya existen períodos con calificaciones
        $periodosConCalificaciones = Periodo::where('ciclo_escolar_id', $cicloEscolar->id)
            ->whereHas('calificaciones')
            ->exists();

        if ($periodosConCalificaciones) {
            return response()->json([
                'message' => 'No se pueden regenerar los períodos porque ya existen calificaciones registradas',
                'errors' => ['tipo' => ['Elimine las calificaciones existentes antes de regenerar los períodos']]
            ], 422);
        }

        $tipo = $request->tipo;

        // Determinar cantidad de períodos según el tipo
        $cantidades = [
            'bimestre' => 5,
            'trimestre' => 3,
            'cuatrimestre' => 3,
            'semestre' => 2,
            'anual' => 1,
        ];

        $cantidad = $cantidades[$tipo];

        // Nombres de los períodos
        $nombresBase = [
            'bimestre' => ['1er Bimestre', '2do Bimestre', '3er Bimestre', '4to Bimestre', '5to Bimestre'],
            'trimestre' => ['1er Trimestre', '2do Trimestre', '3er Trimestre'],
            'cuatrimestre' => ['1er Cuatrimestre', '2do Cuatrimestre', '3er Cuatrimestre'],
            'semestre' => ['1er Semestre', '2do Semestre'],
            'anual' => ['Anual'],
        ];

        $nombres = $nombresBase[$tipo];

        // Calcular fechas
        $fechaInicio = Carbon::parse($cicloEscolar->fecha_inicio);
        $fechaFin = Carbon::parse($cicloEscolar->fecha_fin);
        $totalDias = $fechaInicio->diffInDays($fechaFin);
        $diasPorPeriodo = (int) floor($totalDias / $cantidad);

        DB::beginTransaction();

        try {
            // Eliminar períodos existentes (ya verificamos que no tienen calificaciones)
            Periodo::where('ciclo_escolar_id', $cicloEscolar->id)->delete();

            $periodos = [];
            $inicioActual = $fechaInicio->copy();

            for ($i = 0; $i < $cantidad; $i++) {
                $esUltimo = ($i === $cantidad - 1);

                // El último período termina exactamente en la fecha fin del ciclo
                $finActual = $esUltimo
                    ? $fechaFin->copy()
                    : $inicioActual->copy()->addDays($diasPorPeriodo - 1);

                $periodo = Periodo::create([
                    'ciclo_escolar_id' => $cicloEscolar->id,
                    'nombre' => $nombres[$i],
                    'numero' => $i + 1,
                    'tipo' => $tipo,
                    'fecha_inicio' => $inicioActual->format('Y-m-d'),
                    'fecha_fin' => $finActual->format('Y-m-d'),
                    'activo' => true,
                ]);

                $periodos[] = $periodo;

                // El siguiente período inicia el día después del fin actual
                $inicioActual = $finActual->copy()->addDay();
            }

            DB::commit();

            return response()->json([
                'message' => 'Períodos generados exitosamente',
                'data' => $periodos
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al generar los períodos',
                'errors' => ['general' => [$e->getMessage()]]
            ], 500);
        }
    }
}
