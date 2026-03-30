<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantillaPlanPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'plantillas_plan_pago';

    protected $fillable = [
        'escuela_id',
        'nivel_id',
        'nombre',
        'descripcion',
        'es_sistema',
        'activo',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Scope para filtrar por escuela
     * Las plantillas del sistema (es_sistema=true) son visibles para todos
     */
    public function scopeForEscuela($query, $escuelaId)
    {
        return $query->where(function ($q) use ($escuelaId) {
            $q->where('escuela_id', $escuelaId)
              ->orWhere('es_sistema', true);
        });
    }

    /**
     * Scope para plantillas del sistema
     */
    public function scopeSistema($query)
    {
        return $query->where('es_sistema', true);
    }

    /**
     * Scope para plantillas personalizadas
     */
    public function scopePersonalizadas($query, $escuelaId)
    {
        return $query->where('escuela_id', $escuelaId)
                     ->where('es_sistema', false);
    }

    /**
     * Relaciones
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(ConceptoPlantilla::class, 'plantilla_plan_pago_id')->orderBy('orden');
    }
}
