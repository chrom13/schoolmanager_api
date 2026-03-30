<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConceptoPrecio extends Model
{
    use HasFactory;

    protected $table = 'concepto_precios';

    protected $fillable = [
        'concepto_type',
        'concepto_id',
        'tipo',
        'desde_fecha',
        'hasta_fecha',
        'desde_dias',
        'hasta_dias',
        'monto',
        'descripcion',
        'orden',
    ];

    protected $casts = [
        'desde_fecha' => 'date',
        'hasta_fecha' => 'date',
        'desde_dias' => 'integer',
        'hasta_dias' => 'integer',
        'monto' => 'decimal:2',
        'orden' => 'integer',
    ];

    /**
     * Relación polimórfica con el concepto (puede ser ConceptoPlantilla o ConceptoPlanPago)
     */
    public function concepto(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determina si este precio aplica para una fecha dada
     */
    public function aplicaParaFecha(\DateTimeInterface $fecha): bool
    {
        if ($this->tipo === 'fecha_fija') {
            return $fecha >= $this->desde_fecha && $fecha <= $this->hasta_fecha;
        }

        return false;
    }

    /**
     * Determina si este precio aplica para una cantidad de días relativa al vencimiento
     */
    public function aplicaParaDias(int $diasRelativosAlVencimiento): bool
    {
        if ($this->tipo === 'dias_vencimiento') {
            return $diasRelativosAlVencimiento >= $this->desde_dias
                && $diasRelativosAlVencimiento <= $this->hasta_dias;
        }

        return false;
    }

    /**
     * Scope para ordenar precios
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('orden');
    }
}
