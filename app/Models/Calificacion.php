<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calificacion extends Model
{
    use HasFactory;

    protected $table = 'calificaciones';

    protected $fillable = [
        'alumno_id',
        'materia_id',
        'periodo_id',
        'calificacion',
        'observaciones',
        'maestro_id',
    ];

    protected $casts = [
        'calificacion' => 'decimal:2',
    ];

    /**
     * Relación con Alumno
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /**
     * Relación con Materia
     */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /**
     * Relación con Período
     */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    /**
     * Relación con Maestro
     */
    public function maestro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maestro_id');
    }
}
