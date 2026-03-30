<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConceptoPlanPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'conceptos_plan_pago';

    protected $fillable = [
        'plan_pago_id',
        'concepto',
        'descripcion',
        'orden',
        'monto_base',
        'monto_pronto_pago',
        'monto_recargo',
        'fecha_vencimiento',
        'fecha_pronto_pago',
        'fecha_recargo',
        'activo',
    ];

    protected $casts = [
        'monto_base'        => 'decimal:2',
        'monto_pronto_pago' => 'decimal:2',
        'monto_recargo'     => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pronto_pago' => 'date',
        'fecha_recargo'     => 'date',
        'activo'            => 'boolean',
        'orden'             => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function planPago(): BelongsTo
    {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }

    public function precios(): MorphMany
    {
        return $this->morphMany(ConceptoPrecio::class, 'concepto')->orderBy('orden');
    }
}
