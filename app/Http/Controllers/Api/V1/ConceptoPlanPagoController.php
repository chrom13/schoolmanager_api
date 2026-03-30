<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConceptoPlanPago;
use App\Models\PlanPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConceptoPlanPagoController extends Controller
{
    /**
     * Lista todos los conceptos de un plan de pago
     */
    public function index(int $planPagoId): JsonResponse
    {
        $plan = PlanPago::findOrFail($planPagoId);
        $conceptos = $plan->conceptos;

        return response()->json([
            'data' => $conceptos
        ]);
    }

    /**
     * Crear nuevo concepto en un plan
     */
    public function store(Request $request, int $planPagoId): JsonResponse
    {
        $plan = PlanPago::findOrFail($planPagoId);

        $request->validate([
            'concepto' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'monto_base' => ['required', 'numeric', 'min:0'],
            'monto_pronto_pago' => ['nullable', 'numeric', 'min:0'],
            'monto_recargo' => ['nullable', 'numeric', 'min:0'],
            'fecha_vencimiento' => ['required', 'date'],
            'fecha_pronto_pago' => ['nullable', 'date', 'before:fecha_vencimiento'],
            'fecha_recargo' => ['nullable', 'date', 'after:fecha_vencimiento'],
        ]);

        $concepto = ConceptoPlanPago::create([
            'plan_pago_id' => $planPagoId,
            ...$request->all()
        ]);

        return response()->json([
            'message' => 'Concepto creado exitosamente',
            'data' => $concepto
        ], 201);
    }

    /**
     * Mostrar un concepto específico
     */
    public function show(int $planPagoId, ConceptoPlanPago $concepto): JsonResponse
    {
        // Verificar que el concepto pertenece al plan
        if ($concepto->plan_pago_id != $planPagoId) {
            return response()->json([
                'message' => 'El concepto no pertenece a este plan'
            ], 404);
        }

        return response()->json([
            'data' => $concepto
        ]);
    }

    /**
     * Actualizar concepto
     */
    public function update(Request $request, int $planPagoId, ConceptoPlanPago $concepto): JsonResponse
    {
        // Verificar que el concepto pertenece al plan
        if ($concepto->plan_pago_id != $planPagoId) {
            return response()->json([
                'message' => 'El concepto no pertenece a este plan'
            ], 404);
        }

        $request->validate([
            'concepto' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'monto_base' => ['sometimes', 'numeric', 'min:0'],
            'monto_pronto_pago' => ['nullable', 'numeric', 'min:0'],
            'monto_recargo' => ['nullable', 'numeric', 'min:0'],
            'fecha_vencimiento' => ['sometimes', 'date'],
            'fecha_pronto_pago' => ['nullable', 'date'],
            'fecha_recargo' => ['nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $concepto->update($request->all());

        return response()->json([
            'message' => 'Concepto actualizado exitosamente',
            'data' => $concepto
        ]);
    }

    /**
     * Eliminar concepto
     */
    public function destroy(int $planPagoId, ConceptoPlanPago $concepto): JsonResponse
    {
        // Verificar que el concepto pertenece al plan
        if ($concepto->plan_pago_id != $planPagoId) {
            return response()->json([
                'message' => 'El concepto no pertenece a este plan'
            ], 404);
        }

        // Verificar que no tenga cuentas por cobrar asociadas
        $tieneCuentas = \App\Models\CuentaPorCobrar::where('concepto_plan_id', $concepto->id)->exists();

        if ($tieneCuentas) {
            return response()->json([
                'message' => 'No se puede eliminar el concepto porque tiene cuentas por cobrar asociadas'
            ], 422);
        }

        $concepto->delete();

        return response()->json([
            'message' => 'Concepto eliminado exitosamente'
        ]);
    }
}
