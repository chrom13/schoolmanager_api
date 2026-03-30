<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\PlanPago;
use App\Models\CuentaPorCobrar;
use Illuminate\Support\Facades\DB;

class ServicioGeneradorPagos
{
    /**
     * Asigna un plan de pago a un alumno
     * Crea todas las cuentas por cobrar basadas en los conceptos del plan
     *
     * @param Alumno $alumno
     * @param PlanPago $plan
     * @return \Illuminate\Support\Collection
     * @throws \Exception
     */
    public function asignarPlanAAlumno(Alumno $alumno, PlanPago $plan)
    {
        return DB::transaction(function () use ($alumno, $plan) {
            // Cargar los conceptos del plan
            $conceptos = $plan->conceptos()->where('activo', true)->get();

            if ($conceptos->isEmpty()) {
                throw new \Exception('El plan de pago no tiene conceptos activos');
            }

            $cuentasPorCobrar = [];

            foreach ($conceptos as $concepto) {
                $cuenta = CuentaPorCobrar::create([
                    'escuela_id' => $alumno->escuela_id,
                    'alumno_id' => $alumno->id,
                    'concepto_plan_id' => $concepto->id,
                    'concepto' => $concepto->concepto,
                    'descripcion' => $concepto->descripcion,

                    // Copiar montos
                    'monto_base' => $concepto->monto_base,
                    'monto_pronto_pago' => $concepto->monto_pronto_pago,
                    'monto_recargo' => $concepto->monto_recargo,

                    // Copiar fechas
                    'fecha_vencimiento' => $concepto->fecha_vencimiento,
                    'fecha_pronto_pago' => $concepto->fecha_pronto_pago,
                    'fecha_recargo' => $concepto->fecha_recargo,

                    // Estado inicial
                    'estado' => 'pendiente',
                    'monto_pagado' => 0,
                    'saldo' => $concepto->monto_base,
                    'es_cargo_suelto' => false,
                ]);

                $cuentasPorCobrar[] = $cuenta;
            }

            return collect($cuentasPorCobrar);
        });
    }

    /**
     * Crea un cargo suelto (ad-hoc) para un alumno
     * No está vinculado a ningún plan
     *
     * @param Alumno $alumno
     * @param array $datos
     * @return CuentaPorCobrar
     */
    public function crearCargoSuelto(Alumno $alumno, array $datos)
    {
        return CuentaPorCobrar::create([
            'escuela_id' => $alumno->escuela_id,
            'alumno_id' => $alumno->id,
            'concepto_plan_id' => null,
            'concepto' => $datos['concepto'],
            'descripcion' => $datos['descripcion'] ?? null,

            // Montos
            'monto_base' => $datos['monto_base'],
            'monto_pronto_pago' => $datos['monto_pronto_pago'] ?? null,
            'monto_recargo' => $datos['monto_recargo'] ?? null,

            // Fechas
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'fecha_pronto_pago' => $datos['fecha_pronto_pago'] ?? null,
            'fecha_recargo' => $datos['fecha_recargo'] ?? null,

            // Estado inicial
            'estado' => 'pendiente',
            'monto_pagado' => 0,
            'saldo' => $datos['monto_base'],
            'es_cargo_suelto' => true,
            'notas' => $datos['notas'] ?? null,
        ]);
    }

    /**
     * Registra un pago parcial o total para una cuenta por cobrar
     *
     * @param CuentaPorCobrar $cuenta
     * @param float $montoPago
     * @param string $fechaPago
     * @return CuentaPorCobrar
     */
    public function registrarPago(CuentaPorCobrar $cuenta, float $montoPago, string $fechaPago = null)
    {
        $fechaPago = $fechaPago ?? now()->toDateString();

        $nuevoMontoPagado = $cuenta->monto_pagado + $montoPago;
        $montoActual = $cuenta->monto_actual; // Esto calcula dinámicamente según fechas
        $nuevoSaldo = $montoActual - $nuevoMontoPagado;

        $cuenta->update([
            'monto_pagado' => $nuevoMontoPagado,
            'saldo' => max(0, $nuevoSaldo),
            'fecha_pago' => $fechaPago,
            'estado' => $nuevoSaldo <= 0 ? 'pagado' : 'pendiente',
        ]);

        return $cuenta->fresh();
    }
}
