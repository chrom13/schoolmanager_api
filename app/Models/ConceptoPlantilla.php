<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConceptoPlantilla extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'conceptos_plantilla';

    protected $fillable = [
        'plantilla_plan_pago_id',
        'concepto',
        'descripcion',
        'orden',
        'tipo_concepto',
        'mes_relativo',
        'monto_sugerido',
        'dia_vencimiento',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'mes_relativo' => 'integer',
        'monto_sugerido' => 'decimal:2',
        'dia_vencimiento' => 'integer',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaPlanPago::class, 'plantilla_plan_pago_id');
    }

    public function precios(): MorphMany
    {
        return $this->morphMany(ConceptoPrecio::class, 'concepto')->orderBy('orden');
    }
}
