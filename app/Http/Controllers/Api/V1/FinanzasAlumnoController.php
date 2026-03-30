<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\CuentaPorCobrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanzasAlumnoController extends Controller
{
    /**
     * Obtiene el estado de cuenta de un alumno
     * Retorna todas las cuentas por cobrar con cálculos dinámicos
     *
     * @param int $alumnoId
     * @return JsonResponse
     */
    public function index(int $alumnoId): JsonResponse
    {
        $alumno = Alumno::findOrFail($alumnoId);

        $cuentas = CuentaPorCobrar::where('alumno_id', $alumnoId)
            ->with('conceptoPlan')
            ->orderBy('fecha_vencimiento')
            ->get();

        // Enriquecer los datos con los atributos calculados
        $cuentasConCalculo = $cuentas->map(function ($cuenta) {
            return [
                'id' => $cuenta->id,
                'concepto' => $cuenta->concepto,
                'descripcion' => $cuenta->descripcion,
                'es_cargo_suelto' => $cuenta->es_cargo_suelto,

                // Montos
                'monto_base' => $cuenta->monto_base,
                'monto_pronto_pago' => $cuenta->monto_pronto_pago,
                'monto_recargo' => $cuenta->monto_recargo,
                'monto_actual' => $cuenta->monto_actual, // Calculado dinámicamente
                'monto_pagado' => $cuenta->monto_pagado,
                'saldo' => $cuenta->saldo,

                // Fechas
                'fecha_vencimiento' => $cuenta->fecha_vencimiento?->format('Y-m-d'),
                'fecha_pronto_pago' => $cuenta->fecha_pronto_pago?->format('Y-m-d'),
                'fecha_recargo' => $cuenta->fecha_recargo?->format('Y-m-d'),
                'fecha_pago' => $cuenta->fecha_pago?->format('Y-m-d'),

                // Estado
                'estado' => $cuenta->estado,

                // Flags visuales
                'tiene_pronto_pago' => $cuenta->tiene_pronto_pago,
                'tiene_recargo' => $cuenta->tiene_recargo,
                'esta_vencido' => $cuenta->esta_vencido,

                // Metadatos
                'notas' => $cuenta->notas,
                'created_at' => $cuenta->created_at->toIso8601String(),
                'updated_at' => $cuenta->updated_at->toIso8601String(),
            ];
        });

        // Calcular totales
        $totalAdeudo = $cuentasConCalculo->where('estado', '!=', 'pagado')->sum('monto_actual');
        $totalPagado = $cuentasConCalculo->sum('monto_pagado');
        $cuentasPendientes = $cuentasConCalculo->where('estado', 'pendiente')->count();
        $cuentasVencidas = $cuentasConCalculo->where('esta_vencido', true)->count();

        return response()->json([
            'alumno' => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombre_completo,
            ],
            'cuentas' => $cuentasConCalculo,
            'resumen' => [
                'total_adeudo' => $totalAdeudo,
                'total_pagado' => $totalPagado,
                'cuentas_pendientes' => $cuentasPendientes,
                'cuentas_vencidas' => $cuentasVencidas,
            ],
        ]);
    }

    /**
     * Obtiene el detalle de una cuenta por cobrar específica
     *
     * @param int $alumnoId
     * @param int $cuentaId
     * @return JsonResponse
     */
    public function show(int $alumnoId, int $cuentaId): JsonResponse
    {
        $cuenta = CuentaPorCobrar::where('alumno_id', $alumnoId)
            ->where('id', $cuentaId)
            ->with(['alumno', 'conceptoPlan'])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $cuenta->id,
                'alumno' => [
                    'id' => $cuenta->alumno->id,
                    'nombre_completo' => $cuenta->alumno->nombre_completo,
                ],
                'concepto' => $cuenta->concepto,
                'descripcion' => $cuenta->descripcion,
                'es_cargo_suelto' => $cuenta->es_cargo_suelto,

                // Montos
                'monto_base' => $cuenta->monto_base,
                'monto_pronto_pago' => $cuenta->monto_pronto_pago,
                'monto_recargo' => $cuenta->monto_recargo,
                'monto_actual' => $cuenta->monto_actual,
                'monto_pagado' => $cuenta->monto_pagado,
                'saldo' => $cuenta->saldo,

                // Fechas
                'fecha_vencimiento' => $cuenta->fecha_vencimiento?->format('Y-m-d'),
                'fecha_pronto_pago' => $cuenta->fecha_pronto_pago?->format('Y-m-d'),
                'fecha_recargo' => $cuenta->fecha_recargo?->format('Y-m-d'),
                'fecha_pago' => $cuenta->fecha_pago?->format('Y-m-d'),

                // Estado
                'estado' => $cuenta->estado,

                // Flags
                'tiene_pronto_pago' => $cuenta->tiene_pronto_pago,
                'tiene_recargo' => $cuenta->tiene_recargo,
                'esta_vencido' => $cuenta->esta_vencido,

                // Metadatos
                'notas' => $cuenta->notas,
                'created_at' => $cuenta->created_at->toIso8601String(),
                'updated_at' => $cuenta->updated_at->toIso8601String(),
            ],
        ]);
    }
}
