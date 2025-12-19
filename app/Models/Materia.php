<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Materia extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'escuela_id',
        'nombre',
        'clave',
        'descripcion',
        'color',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación many-to-many con Grupos
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'materia_grupo')
            ->withPivot(['maestro_id', 'horas_semanales', 'activo'])
            ->withTimestamps();
    }
}
