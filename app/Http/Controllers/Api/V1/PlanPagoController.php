<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CicloEscolar;
use App\Models\ConceptoPlanPago;
use App\Models\ConceptoPlantilla;
use App\Models\PlanPago;
use App\Models\PlantillaPlanPago;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanPagoController extends Controller
{
    /**
     * Lista todos los planes de pago
     * Puede filtrar por ciclo_escolar_id y/o nivel_id
     */
    public function index(Request $request): JsonResponse
    {
        $query = PlanPago::with(['cicloEscolar', 'nivel', 'conceptos']);

        // Filtrar por ciclo escolar
        if ($request->has('ciclo_escolar_id')) {
            $query->where('ciclo_escolar_id', $request->ciclo_escolar_id);
        }

        // Filtrar por nivel
        if ($request->has('nivel_id')) {
            $query->where('nivel_id', $request->nivel_id);
        }

        // Filtrar por estado activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $planes = $query->get();

        return response()->json([
            'data' => $planes
        ]);
    }

    /**
     * Crear nuevo plan de pago
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ciclo_escolar_id' => ['required', 'exists:ciclos_escolares,id'],
            'nivel_id' => ['required', 'exists:niveles,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $plan = PlanPago::create($request->all());

        return response()->json([
            'message' => 'Plan de pago creado exitosamente',
            'data' => $plan->load(['cicloEscolar', 'nivel'])
        ], 201);
    }

    /**
     * Mostrar un plan específico
     */
    public function show(PlanPago $planPago): JsonResponse
    {
        $planPago->load(['cicloEscolar', 'nivel', 'conceptos']);

        return response()->json([
            'data' => $planPago
        ]);
    }

    /**
     * Actualizar plan de pago
     */
    public function update(Request $request, PlanPago $planPago): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $planPago->update($request->only(['nombre', 'descripcion', 'activo']));

        return response()->json([
            'message' => 'Plan de pago actualizado exitosamente',
            'data' => $planPago
        ]);
    }

    /**
     * Eliminar plan de pago
     */
    public function destroy($id): JsonResponse
    {
        $planPago = PlanPago::findOrFail($id);

        // Verificar que no tenga cuentas por cobrar asociadas
        $tieneCuentas = \App\Models\CuentaPorCobrar::whereHas('conceptoPlan', function ($query) use ($planPago) {
            $query->where('plan_pago_id', $planPago->id);
        })->exists();

        if ($tieneCuentas) {
            return response()->json([
                'message' => 'No se puede eliminar el plan porque tiene cuentas por cobrar asociadas'
            ], 422);
        }

        $planPago->delete();

        return response()->json([
            'message' => 'Plan de pago eliminado exitosamente'
        ]);
    }

    /**
     * Crear plan de pago desde una plantilla
     */
    public function createFromTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'plantilla_id' => ['required', 'exists:plantillas_plan_pago,id'],
            'ciclo_escolar_id' => ['required', 'exists:ciclos_escolares,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'montos' => ['required', 'array'],
            'montos.inscripcion' => ['nullable', 'numeric', 'min:0'],
            'montos.colegiatura' => ['nullable', 'numeric', 'min:0'],
            'montos.examen' => ['nullable', 'numeric', 'min:0'],
            'montos.otro' => ['nullable', 'numeric', 'min:0'],
            'aplicar_descuento_pronto_pago' => ['boolean'],
            'aplicar_recargos' => ['boolean'],
            'anio_base' => ['required', 'integer'], // Año para calcular fechas (ej: 2025)
        ]);

        // Obtener la plantilla con sus conceptos
        $plantilla = PlantillaPlanPago::with('conceptos')->findOrFail($request->plantilla_id);

        // Obtener el ciclo escolar para el nivel
        $cicloEscolar = CicloEscolar::findOrFail($request->ciclo_escolar_id);

        // Crear el plan de pago
        $plan = PlanPago::create([
            'ciclo_escolar_id' => $request->ciclo_escolar_id,
            'nivel_id' => $cicloEscolar->nivel_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'activo' => true,
        ]);

        // Crear los conceptos basados en la plantilla
        foreach ($plantilla->conceptos as $conceptoPlantilla) {
            // Determinar el monto base según el tipo de concepto
            $montoBase = 0;
            switch ($conceptoPlantilla->tipo_concepto) {
                case 'inscripcion':
                    $montoBase = $request->montos['inscripcion'] ?? $conceptoPlantilla->monto_sugerido;
                    break;
                case 'colegiatura':
                    $montoBase = $request->montos['colegiatura'] ?? $conceptoPlantilla->monto_sugerido;
                    break;
                case 'examen':
                    $montoBase = $request->montos['examen'] ?? $conceptoPlantilla->monto_sugerido;
                    break;
                case 'otro':
                    $montoBase = $request->montos['otro'] ?? $conceptoPlantilla->monto_sugerido;
                    break;
            }

            // Calcular fecha de vencimiento
            $mesRelativo = $conceptoPlantilla->mes_relativo ?? 1;
            $diaVencimiento = $conceptoPlantilla->dia_vencimiento;
            $fechaVencimiento = Carbon::create($request->anio_base, $mesRelativo, $diaVencimiento);

            // Calcular monto pronto pago
            $montoProntoPago = null;
            $fechaProntoPago = null;
            if ($request->aplicar_descuento_pronto_pago && $conceptoPlantilla->descuento_pronto_pago_porcentaje) {
                $descuento = $montoBase * ($conceptoPlantilla->descuento_pronto_pago_porcentaje / 100);
                $montoProntoPago = $montoBase - $descuento;

                if ($conceptoPlantilla->dias_pronto_pago_antes_vencimiento) {
                    $fechaProntoPago = $fechaVencimiento->copy()->subDays($conceptoPlantilla->dias_pronto_pago_antes_vencimiento);
                }
            }

            // Calcular monto con recargo
            $montoRecargo = null;
            $fechaRecargo = null;
            if ($request->aplicar_recargos && $conceptoPlantilla->recargo_porcentaje) {
                $recargo = $montoBase * ($conceptoPlantilla->recargo_porcentaje / 100);
                $montoRecargo = $montoBase + $recargo;

                if ($conceptoPlantilla->dias_recargo_despues_vencimiento) {
                    $fechaRecargo = $fechaVencimiento->copy()->addDays($conceptoPlantilla->dias_recargo_despues_vencimiento);
                }
            }

            // Reemplazar {mes} en el nombre del concepto si existe
            $nombreConcepto = $conceptoPlantilla->concepto;
            if ($conceptoPlantilla->mes_relativo) {
                $nombreMes = Carbon::create($request->anio_base, $mesRelativo, 1)->locale('es')->monthName;
                $nombreConcepto = str_replace('{mes}', ucfirst($nombreMes), $nombreConcepto);
            }

            // Crear el concepto
            ConceptoPlanPago::create([
                'plan_pago_id' => $plan->id,
                'concepto' => $nombreConcepto,
                'descripcion' => $conceptoPlantilla->descripcion,
                'orden' => $conceptoPlantilla->orden,
                'monto_base' => $montoBase,
                'monto_pronto_pago' => $montoProntoPago,
                'monto_recargo' => $montoRecargo,
                'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                'fecha_pronto_pago' => $fechaProntoPago?->format('Y-m-d'),
                'fecha_recargo' => $fechaRecargo?->format('Y-m-d'),
                'activo' => true,
            ]);
        }

        return response()->json([
            'message' => 'Plan de pago creado exitosamente desde plantilla',
            'data' => $plan->load(['cicloEscolar', 'nivel', 'conceptos'])
        ], 201);
    }

    /**
     * Duplicar un plan de pago existente a un nuevo ciclo escolar
     */
    public function duplicate(Request $request, $id): JsonResponse
    {
        $request->validate([
            'ciclo_escolar_id' => ['required', 'exists:ciclos_escolares,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'ajuste_fechas' => ['required', 'in:sumar_1_anio,mantener'],
            'ajuste_montos' => ['required', 'array'],
            'ajuste_montos.tipo' => ['required', 'in:sin_cambios,porcentaje,manual'],
            'ajuste_montos.valor' => ['nullable', 'numeric'], // Porcentaje de incremento
        ]);

        // Obtener el plan original con sus conceptos
        $planOriginal = PlanPago::with('conceptos')->findOrFail($id);

        // Obtener el ciclo escolar para el nivel
        $cicloEscolar = CicloEscolar::findOrFail($request->ciclo_escolar_id);

        // Crear el nuevo plan
        $nuevoPlan = PlanPago::create([
            'ciclo_escolar_id' => $request->ciclo_escolar_id,
            'nivel_id' => $cicloEscolar->nivel_id,
            'nombre' => $request->nombre,
            'descripcion' => $planOriginal->descripcion,
            'activo' => true,
        ]);

        // Duplicar los conceptos
        foreach ($planOriginal->conceptos as $conceptoOriginal) {
            // Ajustar fechas
            $fechaVencimiento = Carbon::parse($conceptoOriginal->fecha_vencimiento);
            $fechaProntoPago = $conceptoOriginal->fecha_pronto_pago
                ? Carbon::parse($conceptoOriginal->fecha_pronto_pago)
                : null;
            $fechaRecargo = $conceptoOriginal->fecha_recargo
                ? Carbon::parse($conceptoOriginal->fecha_recargo)
                : null;

            if ($request->ajuste_fechas === 'sumar_1_anio') {
                $fechaVencimiento->addYear();
                $fechaProntoPago?->addYear();
                $fechaRecargo?->addYear();
            }

            // Ajustar montos
            $montoBase = $conceptoOriginal->monto_base;
            $montoProntoPago = $conceptoOriginal->monto_pronto_pago;
            $montoRecargo = $conceptoOriginal->monto_recargo;

            if ($request->ajuste_montos['tipo'] === 'porcentaje' && isset($request->ajuste_montos['valor'])) {
                $porcentaje = $request->ajuste_montos['valor'];
                $multiplicador = 1 + ($porcentaje / 100);

                $montoBase = $montoBase * $multiplicador;
                $montoProntoPago = $montoProntoPago ? $montoProntoPago * $multiplicador : null;
                $montoRecargo = $montoRecargo ? $montoRecargo * $multiplicador : null;
            }

            // Crear el nuevo concepto
            ConceptoPlanPago::create([
                'plan_pago_id' => $nuevoPlan->id,
                'concepto' => $conceptoOriginal->concepto,
                'descripcion' => $conceptoOriginal->descripcion,
                'orden' => $conceptoOriginal->orden,
                'monto_base' => round($montoBase, 2),
                'monto_pronto_pago' => $montoProntoPago ? round($montoProntoPago, 2) : null,
                'monto_recargo' => $montoRecargo ? round($montoRecargo, 2) : null,
                'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                'fecha_pronto_pago' => $fechaProntoPago?->format('Y-m-d'),
                'fecha_recargo' => $fechaRecargo?->format('Y-m-d'),
                'activo' => true,
            ]);
        }

        return response()->json([
            'message' => 'Plan de pago duplicado exitosamente',
            'data' => $nuevoPlan->load(['cicloEscolar', 'nivel', 'conceptos'])
        ], 201);
    }

    /**
     * Guardar un plan de pago existente como plantilla personalizada
     */
    public function saveAsTemplate(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
        ]);

        // Obtener el plan con sus conceptos
        $plan = PlanPago::with('conceptos')->findOrFail($id);

        // Crear la plantilla
        $plantilla = PlantillaPlanPago::create([
            'escuela_id' => $plan->escuela_id,
            'nivel_id' => $plan->nivel_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'es_sistema' => false,
            'activo' => true,
        ]);

        // Crear los conceptos de plantilla basados en los conceptos del plan
        foreach ($plan->conceptos as $concepto) {
            // Extraer el mes de la fecha de vencimiento
            $fechaVencimiento = Carbon::parse($concepto->fecha_vencimiento);
            $mesRelativo = $fechaVencimiento->month;
            $diaVencimiento = $fechaVencimiento->day;

            // Determinar tipo de concepto basado en el nombre
            $tipoConcepto = 'otro';
            $nombreLower = strtolower($concepto->concepto);
            if (str_contains($nombreLower, 'inscripción') || str_contains($nombreLower, 'inscripcion')) {
                $tipoConcepto = 'inscripcion';
            } elseif (str_contains($nombreLower, 'colegiatura')) {
                $tipoConcepto = 'colegiatura';
            } elseif (str_contains($nombreLower, 'examen')) {
                $tipoConcepto = 'examen';
            }

            // Calcular porcentajes si existen descuentos/recargos
            $descuentoPorcentaje = null;
            $diasProntoPago = null;
            if ($concepto->monto_pronto_pago && $concepto->fecha_pronto_pago) {
                $diferencia = $concepto->monto_base - $concepto->monto_pronto_pago;
                $descuentoPorcentaje = ($diferencia / $concepto->monto_base) * 100;

                $fechaProntoPago = Carbon::parse($concepto->fecha_pronto_pago);
                $diasProntoPago = $fechaVencimiento->diffInDays($fechaProntoPago);
            }

            $recargoPorcentaje = null;
            $diasRecargo = null;
            if ($concepto->monto_recargo && $concepto->fecha_recargo) {
                $diferencia = $concepto->monto_recargo - $concepto->monto_base;
                $recargoPorcentaje = ($diferencia / $concepto->monto_base) * 100;

                $fechaRecargo = Carbon::parse($concepto->fecha_recargo);
                $diasRecargo = $fechaVencimiento->diffInDays($fechaRecargo);
            }

            // Crear concepto de plantilla
            \App\Models\ConceptoPlantilla::create([
                'plantilla_plan_pago_id' => $plantilla->id,
                'concepto' => $concepto->concepto,
                'descripcion' => $concepto->descripcion,
                'orden' => $concepto->orden,
                'tipo_concepto' => $tipoConcepto,
                'mes_relativo' => $mesRelativo,
                'monto_sugerido' => $concepto->monto_base,
                'dia_vencimiento' => $diaVencimiento,
                'descuento_pronto_pago_porcentaje' => $descuentoPorcentaje ? round($descuentoPorcentaje, 2) : null,
                'dias_pronto_pago_antes_vencimiento' => $diasProntoPago,
                'recargo_porcentaje' => $recargoPorcentaje ? round($recargoPorcentaje, 2) : null,
                'dias_recargo_despues_vencimiento' => $diasRecargo,
                'activo' => true,
            ]);
        }

        return response()->json([
            'message' => 'Plan guardado como plantilla exitosamente',
            'data' => $plantilla->load(['nivel', 'conceptos'])
        ], 201);
    }

    /**
     * Crear un plan de pago desde cero (al vuelo) durante el onboarding.
     * También crea una PlantillaPlanPago reutilizable en la misma transacción.
     *
     * Body:
     * {
     *   ciclo_escolar_id: int,
     *   nombre: string,
     *   descripcion?: string,
     *   conceptos: [{
     *     concepto: string,
     *     tipo: 'inscripcion'|'colegiatura'|'examen'|'otro',
     *     monto_base: float,
     *     fecha_vencimiento: date,         // YYYY-MM-DD
     *     fecha_pronto_pago?: date|null,
     *     monto_pronto_pago?: float|null,
     *     fecha_recargo?: date|null,
     *     monto_recargo?: float|null,
     *     orden: int
     *   }]
     * }
     */
    public function crearAlVuelo(Request $request): JsonResponse
    {
        $request->validate([
            'ciclo_escolar_id'            => ['required', 'exists:ciclos_escolares,id'],
            'nombre'                      => ['required', 'string', 'max:255'],
            'descripcion'                 => ['nullable', 'string'],
            'conceptos'                   => ['required', 'array', 'min:1'],
            'conceptos.*.concepto'        => ['required', 'string', 'max:255'],
            'conceptos.*.tipo'            => ['required', 'in:inscripcion,colegiatura,examen,otro'],
            'conceptos.*.monto_base'      => ['required', 'numeric', 'min:0'],
            'conceptos.*.fecha_vencimiento' => ['required', 'date'],
            'conceptos.*.fecha_pronto_pago' => ['nullable', 'date', 'before:conceptos.*.fecha_vencimiento'],
            'conceptos.*.monto_pronto_pago' => ['nullable', 'numeric', 'min:0'],
            'conceptos.*.fecha_recargo'   => ['nullable', 'date', 'after:conceptos.*.fecha_vencimiento'],
            'conceptos.*.monto_recargo'   => ['nullable', 'numeric', 'min:0'],
            'conceptos.*.orden'           => ['required', 'integer', 'min:1'],
        ]);

        $ciclo = CicloEscolar::findOrFail($request->ciclo_escolar_id);

        $plan = DB::transaction(function () use ($request, $ciclo) {
            // 1. Crear PlantillaPlanPago reutilizable
            $plantilla = PlantillaPlanPago::create([
                'escuela_id'  => auth()->user()->escuela_id,
                'nivel_id'    => $ciclo->nivel_id,
                'nombre'      => $request->nombre . ' (plantilla)',
                'descripcion' => $request->descripcion,
                'es_sistema'  => false,
                'activo'      => true,
            ]);

            // 2. Crear conceptos de plantilla
            foreach ($request->conceptos as $conceptoData) {
                $fechaVenc = Carbon::parse($conceptoData['fecha_vencimiento']);

                ConceptoPlantilla::create([
                    'plantilla_plan_pago_id' => $plantilla->id,
                    'concepto'               => $conceptoData['concepto'],
                    'orden'                  => $conceptoData['orden'],
                    'tipo_concepto'          => $conceptoData['tipo'],
                    'mes_relativo'           => $fechaVenc->month,
                    'monto_sugerido'         => $conceptoData['monto_base'],
                    'dia_vencimiento'        => $fechaVenc->day,
                    'activo'                 => true,
                ]);
            }

            // 3. Crear el PlanPago concreto
            $plan = PlanPago::create([
                'ciclo_escolar_id' => $ciclo->id,
                'nivel_id'         => $ciclo->nivel_id,
                'nombre'           => $request->nombre,
                'descripcion'      => $request->descripcion,
                'activo'           => true,
            ]);

            // 4. Crear conceptos del plan con fechas y montos reales
            foreach ($request->conceptos as $conceptoData) {
                ConceptoPlanPago::create([
                    'plan_pago_id'      => $plan->id,
                    'concepto'          => $conceptoData['concepto'],
                    'orden'             => $conceptoData['orden'],
                    'monto_base'        => $conceptoData['monto_base'],
                    'monto_pronto_pago' => $conceptoData['monto_pronto_pago'] ?? null,
                    'monto_recargo'     => $conceptoData['monto_recargo'] ?? null,
                    'fecha_vencimiento' => $conceptoData['fecha_vencimiento'],
                    'fecha_pronto_pago' => $conceptoData['fecha_pronto_pago'] ?? null,
                    'fecha_recargo'     => $conceptoData['fecha_recargo'] ?? null,
                    'activo'            => true,
                ]);
            }

            return $plan;
        });

        return response()->json([
            'message' => 'Plan de pago creado exitosamente',
            'data'    => $plan->load(['cicloEscolar', 'nivel', 'conceptos']),
        ], 201);
    }
}
