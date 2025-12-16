<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConceptoCobro extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'conceptos_cobro';

    protected $fillable = [
        'escuela_id',
        'nombre',
        'descripcion',
        'precio_base',
        'periodicidad',
        'nivel_id',
        'grado_id',
        'activo',
    ];

    protected $casts = [
        'precio_base' => 'decimal:2',
        'activo' => 'boolean',
    ];

    /**
     * Relación con Nivel (opcional)
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    /**
     * Relación con Grado (opcional)
     */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }
}
