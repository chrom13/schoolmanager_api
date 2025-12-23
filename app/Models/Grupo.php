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
        'nombre',
        'capacidad_maxima',
        'maestro_id',
    ];

    protected $casts = [
        'capacidad_maxima' => 'integer',
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

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maestro_id');
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(Materia::class, 'materia_grupo')
            ->withPivot(['maestro_id', 'horas_semanales', 'activo'])
            ->withTimestamps();
    }
}
