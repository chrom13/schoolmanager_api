<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nivel extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'niveles';

    protected $fillable = [
        'escuela_id',
        'nombre',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function grados(): HasMany
    {
        return $this->hasMany(Grado::class);
    }

    /**
     * Relación con Ciclos Escolares
     */
    public function ciclosEscolares(): HasMany
    {
        return $this->hasMany(CicloEscolar::class);
    }

    /**
     * Relación hasManyThrough para obtener todos los grupos del nivel
     */
    public function grupos()
    {
        return $this->hasManyThrough(Grupo::class, Grado::class);
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('escuela_id', auth()->user()->escuela_id)
            ->firstOrFail();
    }
}
