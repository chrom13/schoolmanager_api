<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Materia extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'escuela_id',
        'nombre',
        'clave',
        'descripcion',
        'color',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
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
