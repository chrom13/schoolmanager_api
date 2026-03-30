<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CuentaPorCobrar extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'cuentas_por_cobrar';

    protected $fillable = [
        'escuela_id',
        'alumno_id',
        'concepto_plan_id',
        'concepto',
        'descripcion',
        'monto_base',
        'monto_pronto_pago',
        'monto_recargo',
        'fecha_vencimiento',
        'fecha_pronto_pago',
        'fecha_recargo',
        'estado',
        'monto_pagado',
        'saldo',
        'fecha_pago',
        'es_cargo_suelto',
        'notas',
    ];

    protected $casts = [
        'monto_base' => 'decimal:2',
        'monto_pronto_pago' => 'decimal:2',
        'monto_recargo' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'saldo' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pronto_pago' => 'date',
        'fecha_recargo' => 'date',
        'fecha_pago' => 'date',
        'es_cargo_suelto' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'monto_actual',
        'tiene_pronto_pago',
        'tiene_recargo',
        'esta_vencido',
    ];

    /**
     * Relaciones
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function conceptoPlan(): BelongsTo
    {
        return $this->belongsTo(ConceptoPlanPago::class, 'concepto_plan_id');
    }

    /**
     * Calcula el monto a pagar HOY basándose en las fechas
     * Lógica:
     * - Si hoy <= fecha_pronto_pago → monto_pronto_pago
     * - Si hoy >= fecha_recargo → monto_base + monto_recargo
     * - Caso contrario → monto_base
     */
    protected function montoActual(): Attribute
    {
        return Attribute::make(
            get: function () {
                $hoy = Carbon::today();

                // Si ya está pagado, retornar 0
                if ($this->estado === 'pagado') {
                    return 0;
                }

                // Verificar pronto pago
                if ($this->fecha_pronto_pago && $hoy->lte($this->fecha_pronto_pago) && $this->monto_pronto_pago) {
                    return $this->monto_pronto_pago;
                }

                // Verificar recargo
                if ($this->fecha_recargo && $hoy->gte($this->fecha_recargo) && $this->monto_recargo) {
                    return $this->monto_base + $this->monto_recargo;
                }

                // Precio normal
                return $this->monto_base;
            }
        );
    }

    /**
     * Indica si actualmente tiene disponible el pronto pago
     */
    protected function tieneProntoPago(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->fecha_pronto_pago || !$this->monto_pronto_pago) {
                    return false;
                }

                return Carbon::today()->lte($this->fecha_pronto_pago);
            }
        );
    }

    /**
     * Indica si actualmente tiene recargo aplicado
     */
    protected function tieneRecargo(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->fecha_recargo || !$this->monto_recargo) {
                    return false;
                }

                return Carbon::today()->gte($this->fecha_recargo);
            }
        );
    }

    /**
     * Indica si el cargo está vencido
     */
    protected function estaVencido(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->estado === 'pagado') {
                    return false;
                }

                return Carbon::today()->gt($this->fecha_vencimiento);
            }
        );
    }
}
