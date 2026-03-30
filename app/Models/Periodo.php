<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periodo extends Model
{
    use HasFactory;

    protected $fillable = [
        'ciclo_escolar_id',
        'nombre',
        'numero',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
        'numero' => 'integer',
    ];

    /**
     * Relación con Ciclo Escolar
     */
    public function cicloEscolar(): BelongsTo
    {
        return $this->belongsTo(CicloEscolar::class);
    }

    /**
     * Relación con Calificaciones
     */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class);
    }
}
