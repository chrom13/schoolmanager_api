<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'grupos';

    protected $fillable = [
        'escuela_id',
        'grado_id',
        'ciclo_escolar_id',
        'nombre',
        'capacidad_maxima',
        'capacidad_ideal',
        'maestro_id',
    ];

    protected $casts = [
        'capacidad_maxima' => 'integer',
        'capacidad_ideal' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    public function cicloEscolar(): BelongsTo
    {
        return $this->belongsTo(CicloEscolar::class);
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maestro_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'inscripciones')
            ->withPivot(['fecha_inscripcion', 'estado', 'observaciones'])
            ->withTimestamps();
    }

    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(Materia::class, 'materia_grupo')
            ->withPivot(['maestro_id', 'horas_semanales', 'activo'])
            ->withTimestamps();
    }
}
